<?php

namespace Tests\Feature\ERP;

use App\DTOs\ERP\Monitoring\ErpSyncHealthSnapshot;
use App\Services\ERP\Monitoring\ErpSyncHealthService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class ErpSyncHealthCommandTest extends TestCase
{
    public function test_healthy_report_returns_success(): void
    {
        $this->bindSnapshot(
            $this->snapshot(
                status: 'healthy'
            )
        );

        $this
            ->artisan('erp:sync:health')
            ->expectsOutputToContain('healthy')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_degraded_report_returns_success_by_default(): void
    {
        $this->bindSnapshot(
            $this->snapshot(
                status: 'degraded',
                reasons: [
                    'One checkpoint is stale.',
                ]
            )
        );

        $this
            ->artisan('erp:sync:health')
            ->expectsOutputToContain('degraded')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_degraded_report_can_fail_for_probes(): void
    {
        $this->bindSnapshot(
            $this->snapshot(
                status: 'degraded',
                reasons: [
                    'One checkpoint is stale.',
                ]
            )
        );

        $this
            ->artisan(
                'erp:sync:health',
                [
                    '--fail-on-degraded' => true,
                ]
            )
            ->assertExitCode(Command::FAILURE);
    }

    public function test_unhealthy_report_returns_failure(): void
    {
        $this->bindSnapshot(
            $this->snapshot(
                status: 'unhealthy',
                reasons: [
                    'The latest synchronization failed.',
                ]
            )
        );

        $this
            ->artisan('erp:sync:health')
            ->expectsOutputToContain('unhealthy')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_json_output_contains_safe_health_fields(): void
    {
        $this->bindSnapshot(
            $this->snapshot(
                status: 'healthy'
            )
        );

        $exitCode = Artisan::call(
            'erp:sync:health',
            [
                '--json' => true,
            ]
        );

        $output = trim(
            Artisan::output()
        );

        $decoded = json_decode(
            $output,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            Command::SUCCESS,
            $exitCode
        );

        $this->assertIsArray(
            $decoded
        );

        $this->assertSame(
            'healthy',
            $decoded['status']
        );

        $this->assertSame(
            'simulated_sage',
            $decoded['source_system']
        );

        $this->assertSame(
            45,
            $decoded['stale_after_minutes']
        );

        $this->assertArrayHasKey(
            'latest_run',
            $decoded
        );

        $this->assertArrayHasKey(
            'summary',
            $decoded
        );

        $this->assertArrayHasKey(
            'resources',
            $decoded
        );

        $this->assertArrayHasKey(
            'recent_failures',
            $decoded
        );

        $this->assertArrayHasKey(
            'reasons',
            $decoded
        );

        /*
         * Sensitive connector information must never be included in
         * monitoring output.
         */
        $this->assertStringNotContainsString(
            'ERP_API_TOKEN',
            $output
        );

        $this->assertStringNotContainsString(
            'Authorization',
            $output
        );

        $this->assertStringNotContainsString(
            'Bearer ',
            $output
        );

        $this->assertStringNotContainsString(
            'safe_context',
            $output
        );
    }

    public function test_invalid_stale_threshold_is_rejected(): void
    {
        $service = Mockery::mock(
            ErpSyncHealthService::class
        );

        $service->shouldNotReceive(
            'build'
        );

        $this->app->instance(
            ErpSyncHealthService::class,
            $service
        );

        $this
            ->artisan(
                'erp:sync:health',
                [
                    '--stale-after' => 0,
                ]
            )
            ->assertExitCode(Command::INVALID);
    }

    private function bindSnapshot(
        ErpSyncHealthSnapshot $snapshot
    ): void {
        $service = Mockery::mock(
            ErpSyncHealthService::class
        );

        $service
            ->shouldReceive('snapshot')
            ->once()
            ->with(
                'simulated_sage',
                45
            )
            ->andReturn(
                $snapshot
            );

        $this->app->instance(
            ErpSyncHealthService::class,
            $service
        );
    }

    /**
     * @param list<string> $reasons
     */
    private function snapshot(
        string $status,
        array $reasons = []
    ): ErpSyncHealthSnapshot {
        return new ErpSyncHealthSnapshot(
            status: $status,

            generatedAt: CarbonImmutable::parse(
                '2026-08-01 01:00:00 UTC'
            ),

            sourceSystem: 'simulated_sage',

            staleAfterMinutes: 45,

            latestRun: [
                'run_uuid' =>
                    '00000000-0000-4000-8000-000000000001',

                'status' =>
                    $status === 'unhealthy'
                        ? 'failed'
                        : 'completed',
            ],

            summary: [
                'expected_resources' => 16,
                'registered_states' => 16,
                'missing_states' => 0,
                'fresh_states' => 16,
                'stale_states' => 0,
                'locked_states' => 0,
                'stale_locks' => 0,
                'states_with_failures' => 0,
                'window_hours' => 24,
                'runs_in_window' => 5,
                'failed_runs_in_window' => 0,
                'failures_in_window' => 0,

                'last_successful_run_at' =>
                    '2026-08-01T01:00:00+00:00',

                'minutes_since_success' => 0,
            ],

            resources: [],

            failures: [],

            reasons: $reasons
        );
    }
}