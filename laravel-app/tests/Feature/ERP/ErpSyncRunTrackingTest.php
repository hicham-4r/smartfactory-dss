<?php

namespace Tests\Feature\ERP;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncFailureStage;
use App\Enums\ERP\ErpSyncResourceStatus;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Models\ErpSyncFailure;
use App\Models\ErpSyncRun;
use App\Models\ErpSyncState;
use App\Models\User;
use App\Services\ERP\Sync\ErpSyncRunTracker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class ErpSyncRunTrackingTest extends TestCase
{
    use RefreshDatabase;

    private ErpSyncRunTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tracker = app(
            ErpSyncRunTracker::class
        );
    }

    public function test_synchronization_tracking_schema_exists(): void
    {
        foreach (
            [
                'erp_sync_runs',
                'erp_sync_run_resources',
                'erp_sync_states',
                'erp_sync_failures',
            ] as $table
        ) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Missing table: {$table}"
            );
        }

        $this->assertTrue(
            Schema::hasColumns(
                'erp_sync_states',
                [
                    'source_system',
                    'resource',
                    'resume_page',
                    'resume_cursor',
                    'lock_owner',
                    'lock_acquired_at',
                ]
            )
        );
    }

    public function test_start_run_creates_resource_rows_and_states(): void
    {
        $user = User::factory()->create();

        $run = $this->tracker->startRun(
            sourceSystem:
                'simulated_sage',

            resources: [
                ErpResource::Products,
                ErpResource::Machines,
                ErpResource::Products,
            ],

            trigger:
                ErpSyncTrigger::Manual,

            initiatedByUserId:
                $user->getKey(),

            requestId:
                'sync-request-001'
        );

        $this->assertInstanceOf(
            ErpSyncRun::class,
            $run
        );

        $this->assertSame(
            ErpSyncRunStatus::Running,
            $run->status
        );

        $this->assertSame(
            ErpSyncTrigger::Manual,
            $run->trigger
        );

        $this->assertSame(
            [
                'products',
                'machines',
            ],
            $run->requested_resources
        );

        $this->assertCount(
            2,
            $run->resources
        );

        foreach ($run->resources as $resource) {
            $this->assertSame(
                ErpSyncResourceStatus::Pending,
                $resource->status
            );
        }

        $this->assertDatabaseCount(
            'erp_sync_states',
            2
        );

        $this->assertTrue(
            $run
                ->initiatedBy
                ->is($user)
        );
    }

    public function test_resume_cursor_is_encrypted_in_database(): void
    {
        $run = $this->tracker->startRun(
            sourceSystem:
                'simulated_sage',

            resources: [
                ErpResource::Products,
            ]
        );

        $resource =
            $this->tracker->startResource(
                $run,
                ErpResource::Products
            );

        $rawCursor =
            'opaque-cursor-page-2-secret-value';

        $state =
            $this->tracker->saveCheckpoint(
                runResource: $resource,
                nextPage: 2,
                resumeCursor: $rawCursor,

                lastSourceUpdatedAt:
                    CarbonImmutable::parse(
                        '2026-07-31 10:00:00'
                    ),

                lastSourceVersion: 15
            );

        $this->assertSame(
            $rawCursor,
            $state->resume_cursor
        );

        $storedCursor = DB::table(
            'erp_sync_states'
        )
            ->where(
                'id',
                $state->getKey()
            )
            ->value('resume_cursor');

        $this->assertIsString(
            $storedCursor
        );

        $this->assertNotSame(
            $rawCursor,
            $storedCursor
        );

        $this->assertStringNotContainsString(
            $rawCursor,
            $storedCursor
        );

        $this->assertSame(
            hash('sha256', $rawCursor),
            $state
                ->resume_cursor_fingerprint
        );

        $this->assertArrayNotHasKey(
            'resume_cursor',
            $state->toArray()
        );
    }

    public function test_completed_run_aggregates_resource_counters(): void
    {
        $run = $this->tracker->startRun(
            sourceSystem:
                'simulated_sage',

            resources: [
                ErpResource::Products,
            ]
        );

        $resource =
            $this->tracker->startResource(
                $run,
                ErpResource::Products
            );

        $resource =
            $this->tracker->recordPage(
                runResource: $resource,
                fetched: 10,
                mapped: 10,
                created: 6,
                updated: 3,
                skipped: 1,
                failed: 0
            );

        $this->tracker->saveCheckpoint(
            runResource: $resource,
            nextPage: 2,
            resumeCursor: 'next-products-page',

            lastSourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-31 12:00:00'
                ),

            lastSourceVersion: 30
        );

        $this->tracker->completeResource(
            $resource
        );

        $completed =
            $this->tracker->finalizeRun(
                $run
            );

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $completed->status
        );

        $this->assertSame(
            1,
            $completed->pages_processed
        );

        $this->assertSame(
            10,
            $completed->records_fetched
        );

        $this->assertSame(
            10,
            $completed->records_mapped
        );

        $this->assertSame(
            6,
            $completed->records_created
        );

        $this->assertSame(
            3,
            $completed->records_updated
        );

        $this->assertSame(
            1,
            $completed->records_skipped
        );

        $this->assertSame(
            0,
            $completed->records_failed
        );

        $this->assertNotNull(
            $completed->finished_at
        );

        $state = ErpSyncState::query()
            ->where(
                'source_system',
                'simulated_sage'
            )
            ->where(
                'resource',
                ErpResource::Products->value
            )
            ->firstOrFail();

        $this->assertNotNull(
            $state->last_successful_sync_at
        );

        $this->assertNull(
            $state->resume_cursor
        );

        $this->assertSame(
            1,
            $state->resume_page
        );

        $this->assertSame(
            0,
            $state->consecutive_failures
        );
    }

    public function test_partial_failure_produces_completed_with_errors_and_redacts_secrets(): void
    {
        $run = $this->tracker->startRun(
            sourceSystem:
                'simulated_sage',

            resources: [
                ErpResource::Products,
                ErpResource::Machines,
            ]
        );

        $products =
            $this->tracker->startResource(
                $run,
                ErpResource::Products
            );

        $this->tracker->recordPage(
            runResource: $products,
            fetched: 5,
            mapped: 5,
            created: 2,
            updated: 3,
            skipped: 0,
            failed: 0
        );

        $this->tracker->completeResource(
            $products
        );

        $machines =
            $this->tracker->startResource(
                $run,
                ErpResource::Machines
            );

        $failure =
            $this->tracker->failResource(
                runResource: $machines,

                stage:
                    ErpSyncFailureStage
                        ::Connector,

                errorCode:
                    'http 503',

                errorMessage:
                    'Authorization=super-secret Bearer abc123',

                retryable: true,

                externalId:
                    'MACHINE-001',

                page: 2,

                cursor:
                    'private-cursor-value',

                safeContext: [
                    'status_code' => 503,

                    'authorization' =>
                        'Bearer abc123',

                    'nested' => [
                        'api_key' =>
                            'private-key',

                        'endpoint' =>
                            '/api/erp/v1/machines',
                    ],
                ]
            );

        $completed =
            $this->tracker->finalizeRun(
                $run
            );

        $this->assertSame(
            ErpSyncRunStatus
                ::CompletedWithErrors,

            $completed->status
        );

        $this->assertSame(
            'HTTP_503',
            $failure->error_code
        );

        $this->assertStringNotContainsString(
            'super-secret',
            $failure->error_message
        );

        $this->assertStringNotContainsString(
            'abc123',
            $failure->error_message
        );

        $this->assertSame(
            '[REDACTED]',
            $failure
                ->safe_context[
                    'authorization'
                ]
        );

        $this->assertSame(
            '[REDACTED]',
            $failure
                ->safe_context[
                    'nested'
                ]['api_key']
        );

        $this->assertSame(
            hash(
                'sha256',
                'private-cursor-value'
            ),
            $failure->cursor_fingerprint
        );

        $this->assertDatabaseCount(
            'erp_sync_failures',
            1
        );

        $state = ErpSyncState::query()
            ->where(
                'source_system',
                'simulated_sage'
            )
            ->where(
                'resource',
                ErpResource::Machines->value
            )
            ->firstOrFail();

        $this->assertSame(
            1,
            $state->consecutive_failures
        );

        $this->assertSame(
            'HTTP_503',
            $state->last_error_code
        );
    }

    public function test_run_is_failed_when_every_resource_fails(): void
    {
        $run = $this->tracker->startRun(
            sourceSystem:
                'simulated_sage',

            resources: [
                ErpResource::Products,
            ]
        );

        $resource =
            $this->tracker->startResource(
                $run,
                ErpResource::Products
            );

        $this->tracker->failResource(
            runResource: $resource,

            stage:
                ErpSyncFailureStage::Response,

            errorCode:
                'invalid_response',

            errorMessage:
                'The ERP response was invalid.',

            retryable: false
        );

        $failed =
            $this->tracker->finalizeRun(
                $run
            );

        $this->assertSame(
            ErpSyncRunStatus::Failed,
            $failed->status
        );

        $this->assertSame(
            'RESOURCE_FAILURE',
            $failed->error_code
        );

        $this->assertInstanceOf(
            ErpSyncFailure::class,
            $failed->failures()->first()
        );
    }

    public function test_worker_lease_prevents_concurrent_resource_sync(): void
    {
        $first = $this->tracker
            ->acquireResourceLease(
                sourceSystem:
                    'simulated_sage',

                resource:
                    ErpResource::Products,

                owner:
                    'worker-a',

                timeToLiveSeconds: 300
            );

        $second = $this->tracker
            ->acquireResourceLease(
                sourceSystem:
                    'simulated_sage',

                resource:
                    ErpResource::Products,

                owner:
                    'worker-b',

                timeToLiveSeconds: 300
            );

        $this->assertTrue($first);
        $this->assertFalse($second);

        $wrongRelease = $this->tracker
            ->releaseResourceLease(
                sourceSystem:
                    'simulated_sage',

                resource:
                    ErpResource::Products,

                owner:
                    'worker-b'
            );

        $correctRelease = $this->tracker
            ->releaseResourceLease(
                sourceSystem:
                    'simulated_sage',

                resource:
                    ErpResource::Products,

                owner:
                    'worker-a'
            );

        $third = $this->tracker
            ->acquireResourceLease(
                sourceSystem:
                    'simulated_sage',

                resource:
                    ErpResource::Products,

                owner:
                    'worker-b',

                timeToLiveSeconds: 300
            );

        $this->assertFalse(
            $wrongRelease
        );

        $this->assertTrue(
            $correctRelease
        );

        $this->assertTrue(
            $third
        );

        $state = ErpSyncState::query()
            ->where(
                'source_system',
                'simulated_sage'
            )
            ->where(
                'resource',
                ErpResource::Products->value
            )
            ->firstOrFail();

        $this->assertSame(
            'worker-b',
            $state->lock_owner
        );
    }

    public function test_run_cannot_be_finalized_with_pending_resources(): void
    {
        $run = $this->tracker->startRun(
            sourceSystem:
                'simulated_sage',

            resources: [
                ErpResource::Products,
            ]
        );

        $this->expectException(
            LogicException::class
        );

        $this->tracker->finalizeRun(
            $run
        );
    }
}