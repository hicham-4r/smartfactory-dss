<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpConnectorHealth;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncGroup;
use App\Models\ErpSyncRun;
use App\Models\ErpSyncState;
use App\Services\ERP\Sync\ErpSyncGroupRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpSyncDependencyGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Prevent these tests from contacting the real external
         * Simulated Sage application.
         */
        $this->app->instance(
            ErpConnectorInterface::class,
            new EmptyDependencyValidationConnector()
        );

        config()->set(
            'erp-sync.page_size',
            5
        );

        config()->set(
            'erp-sync.maximum_pages_per_resource',
            100
        );

        config()->set(
            'erp-sync.lease_ttl_seconds',
            300
        );

        config()->set(
            'erp-sync.overlap_seconds',
            300
        );
    }

    public function test_groups_use_dependency_safe_resource_order(): void
    {
        $registry = app(
            ErpSyncGroupRegistry::class
        );

        $this->assertSame(
            [
                'product_families',
                'products',
            ],
            $this->values(
                $registry->resources(
                    ErpSyncGroup::Catalog
                )
            )
        );

        $this->assertSame(
            [
                'production_lines',
                'machines',
                'shifts',
                'operators',
                'operator_assignments',
            ],
            $this->values(
                $registry->resources(
                    ErpSyncGroup::FactoryMaster
                )
            )
        );

        /*
         * Run logs are intentionally excluded. They are local DSS
         * machine telemetry and are not supplied by the external
         * Sage simulator.
         */
        $this->assertSame(
            [
                'work_orders',
                'batches',
                'machine_runs',
            ],
            $this->values(
                $registry->resources(
                    ErpSyncGroup::ProductionExecution
                )
            )
        );

        $this->assertSame(
            [
                'machine_status_events',
                'downtime_events',
                'maintenance_history',
            ],
            $this->values(
                $registry->resources(
                    ErpSyncGroup::Maintenance
                )
            )
        );

        $this->assertSame(
            [
                'finished_lots',
                'inspections',
                'nonconformities',
            ],
            $this->values(
                $registry->resources(
                    ErpSyncGroup::Quality
                )
            )
        );
    }

    public function test_command_lists_all_dependency_groups(): void
    {
        $registry = app(
            ErpSyncGroupRegistry::class
        );

        /*
         * Verify the registered groups directly rather than checking
         * long rendered table cells that may wrap according to the
         * terminal width.
         */
        $groupNames = array_map(
            static fn (
                ErpSyncGroup $group
            ): string => $group->inputName(),

            $registry->groups()
        );

        $this->assertSame(
            [
                'catalog',
                'factory-master',
                'production-execution',
                'maintenance',
                'quality',
            ],
            $groupNames
        );

        /*
         * Verify that the command can render the group catalogue
         * successfully.
         */
        $this
            ->artisan(
                'erp:sync:validate',
                [
                    '--list' => true,
                ]
            )
            ->assertExitCode(
                Command::SUCCESS
            );
    }

    public function test_production_group_is_blocked_without_prerequisites(): void
    {
        /*
         * A production-execution synchronization must not begin
         * before catalog and factory-master checkpoints exist.
         */
        $this
            ->artisan(
                'erp:sync:validate',
                [
                    'group' =>
                        'production-execution',

                    '--from-start' =>
                        true,

                    '--per-page' =>
                        5,
                ]
            )
            ->assertExitCode(
                Command::FAILURE
            );

        /*
         * Prerequisite rejection occurs before a run is created.
         */
        $this->assertDatabaseCount(
            'erp_sync_runs',
            0
        );

        $this->assertDatabaseCount(
            'erp_sync_run_resources',
            0
        );

        $this->assertDatabaseCount(
            'erp_sync_failures',
            0
        );
    }

    public function test_factory_master_group_completes_independently(): void
    {
        $this
            ->artisan(
                'erp:sync:validate',
                [
                    'group' =>
                        'factory-master',

                    '--from-start' =>
                        true,

                    '--per-page' =>
                        5,
                ]
            )
            ->assertExitCode(
                Command::SUCCESS
            );

        $run = ErpSyncRun::query()
            ->with('resources')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            [
                'production_lines',
                'machines',
                'shifts',
                'operators',
                'operator_assignments',
            ],
            $run->requested_resources
        );

        $this->assertSame(
            'completed',
            $run->status->value
        );

        $this->assertNotNull(
            $run->started_at
        );

        $this->assertNotNull(
            $run->finished_at
        );

        $this->assertSame(
            0,
            $run->records_failed
        );

        $this->assertCount(
            5,
            $run->resources
        );

        /*
         * The ErpSyncRun resources relationship is explicitly ordered
         * by its primary key, preserving execution order.
         */
        $this->assertSame(
            [
                'production_lines',
                'machines',
                'shifts',
                'operators',
                'operator_assignments',
            ],
            $run->resources
                ->map(
                    static fn ($resource): string =>
                        $resource->resource->value
                )
                ->values()
                ->all()
        );

        foreach ($run->resources as $resource) {
            $this->assertSame(
                'completed',
                $resource->status->value
            );

            $this->assertSame(
                0,
                $resource->records_failed
            );

            $this->assertNotNull(
                $resource->started_at
            );

            $this->assertNotNull(
                $resource->finished_at
            );
        }

        $this->assertDatabaseCount(
            'erp_sync_runs',
            1
        );

        $this->assertDatabaseCount(
            'erp_sync_run_resources',
            5
        );

        $this->assertDatabaseCount(
            'erp_sync_failures',
            0
        );

        $this->assertSame(
            5,
            ErpSyncState::query()
                ->where(
                    'source_system',
                    'simulated_sage'
                )
                ->whereNotNull(
                    'last_successful_sync_at'
                )
                ->count()
        );

        /*
         * Every synchronization lease must be released after the run.
         */
        $this->assertSame(
            0,
            ErpSyncState::query()
                ->whereNotNull(
                    'lock_owner'
                )
                ->count()
        );
    }

    public function test_all_groups_complete_in_dependency_order(): void
    {
        $registry = app(
            ErpSyncGroupRegistry::class
        );

        $externalResources =
            $registry->allResources();

        /*
         * The groups execute sequentially. Each successful group
         * creates prerequisite checkpoints for the following groups.
         */
        $this
            ->artisan(
                'erp:sync:validate',
                [
                    '--all' =>
                        true,

                    '--from-start' =>
                        true,

                    '--per-page' =>
                        5,

                    '--max-pages' =>
                        20,
                ]
            )
            ->assertExitCode(
                Command::SUCCESS
            );

        $runs = ErpSyncRun::query()
            ->with('resources')
            ->orderBy('id')
            ->get();

        $this->assertCount(
            5,
            $runs
        );

        /*
         * Group 1: catalog.
         */
        $this->assertSame(
            [
                'product_families',
                'products',
            ],
            $runs[0]->requested_resources
        );

        /*
         * Group 2: factory master.
         */
        $this->assertSame(
            [
                'production_lines',
                'machines',
                'shifts',
                'operators',
                'operator_assignments',
            ],
            $runs[1]->requested_resources
        );

        /*
         * Group 3: production execution.
         *
         * Run logs are local telemetry and must not be requested from
         * the external ERP connector.
         */
        $this->assertSame(
            [
                'work_orders',
                'batches',
                'machine_runs',
            ],
            $runs[2]->requested_resources
        );

        /*
         * Group 4: maintenance.
         */
        $this->assertSame(
            [
                'machine_status_events',
                'downtime_events',
                'maintenance_history',
            ],
            $runs[3]->requested_resources
        );

        /*
         * Group 5: quality.
         */
        $this->assertSame(
            [
                'finished_lots',
                'inspections',
                'nonconformities',
            ],
            $runs[4]->requested_resources
        );

        foreach ($runs as $run) {
            $this->assertSame(
                'completed',
                $run->status->value
            );

            $this->assertSame(
                0,
                $run->records_failed
            );

            $this->assertNotNull(
                $run->started_at
            );

            $this->assertNotNull(
                $run->finished_at
            );

            foreach ($run->resources as $resource) {
                $this->assertSame(
                    'completed',
                    $resource->status->value
                );

                $this->assertSame(
                    0,
                    $resource->records_failed
                );

                $this->assertNotNull(
                    $resource->started_at
                );

                $this->assertNotNull(
                    $resource->finished_at
                );
            }
        }

        /*
         * The five group runs contain every externally synchronized
         * ERP resource. RunLogs is deliberately not included.
         */
        $this->assertDatabaseCount(
            'erp_sync_runs',
            5
        );

        $this->assertDatabaseCount(
            'erp_sync_run_resources',
            count($externalResources)
        );

        $this->assertDatabaseCount(
            'erp_sync_failures',
            0
        );

        /*
         * Every external resource must have a successful checkpoint.
         */
        $this->assertSame(
            count($externalResources),

            ErpSyncState::query()
                ->where(
                    'source_system',
                    'simulated_sage'
                )
                ->whereNotNull(
                    'last_successful_sync_at'
                )
                ->count()
        );

        /*
         * Run logs remain local DSS telemetry, so the external Sage
         * synchronization must not create a run-log checkpoint.
         */
        $this->assertDatabaseMissing(
            'erp_sync_states',
            [
                'source_system' =>
                    'simulated_sage',

                'resource' =>
                    ErpResource::RunLogs->value,
            ]
        );

        /*
         * All worker leases must be released.
         */
        $this->assertSame(
            0,
            ErpSyncState::query()
                ->whereNotNull(
                    'lock_owner'
                )
                ->count()
        );

        $this->assertSame(
            0,
            ErpSyncState::query()
                ->whereNotNull(
                    'lock_acquired_at'
                )
                ->count()
        );
    }

    /**
     * @param list<ErpResource> $resources
     *
     * @return list<string>
     */
    private function values(
        array $resources
    ): array {
        return array_map(
            static fn (
                ErpResource $resource
            ): string => $resource->value,

            $resources
        );
    }
}

/**
 * Deterministic connector used only by dependency-group tests.
 *
 * Every external resource returns one empty completed page. This
 * validates grouping, dependency ordering, prerequisite enforcement,
 * run tracking, checkpoints, and lease release without contacting the
 * real Sage simulator.
 */
final class EmptyDependencyValidationConnector implements ErpConnectorInterface
{
    public function name(): string
    {
        return 'Empty dependency validation connector';
    }

    public function sourceSystem(): string
    {
        return 'simulated_sage';
    }

    public function supports(
        ErpResource $resource
    ): bool {
        /*
         * Run logs are local DSS telemetry and are deliberately not
         * exposed by the external Sage connector.
         */
        return $resource !==
            ErpResource::RunLogs;
    }

    public function health(): ErpConnectorHealth
    {
        return new ErpConnectorHealth(
            status:
                ErpConnectorHealthStatus::Healthy,

            checkedAt:
                CarbonImmutable::now(),

            latencyMilliseconds:
                0,

            message:
                'The dependency validation connector is healthy.'
        );
    }

    public function fetchPage(
        ErpResource $resource,
        ErpPageRequest $request
    ): ErpPage {
        return new ErpPage(
            resource:
                $resource,

            records:
                [],

            currentPage:
                $request->page,

            perPage:
                $request->perPage,

            total:
                0,

            nextPage:
                null,

            nextCursor:
                null,

            fetchedAt:
                CarbonImmutable::now(),

            responseId:
                'EMPTY-DEPENDENCY-'
                .$resource->value
                .'-PAGE-'
                .$request->page
        );
    }
}