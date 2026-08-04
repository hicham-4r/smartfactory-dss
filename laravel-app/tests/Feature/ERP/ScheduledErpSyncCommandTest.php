<?php

namespace Tests\Feature\ERP;

use App\Services\ERP\Sync\ArtisanErpGroupSyncRunner;
use App\Services\ERP\Sync\ErpSyncGroupRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ScheduledErpSyncCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cache.default',
            'array'
        );

        config()->set(
            'erp-automation.enabled',
            true
        );

        config()->set(
            'erp-automation.per_page',
            100
        );

        config()->set(
            'erp-automation.max_pages',
            100
        );

        config()->set(
            'erp-automation.lock_key',
            'test:erp:sync-cycle'
        );

        config()->set(
            'erp-automation.lock_seconds',
            300
        );
    }

    public function test_disabled_automation_exits_without_running_groups(): void
    {
        config()->set(
            'erp-automation.enabled',
            false
        );

        $runner = Mockery::mock(
            ArtisanErpGroupSyncRunner::class
        );

        $runner->shouldNotReceive(
            'run'
        );

        $this->app->instance(
            ArtisanErpGroupSyncRunner::class,
            $runner
        );

        $this
            ->artisan(
                'erp:sync:cycle'
            )
            ->expectsOutputToContain(
                'disabled'
            )
            ->assertExitCode(
                Command::SUCCESS
            );
    }

    public function test_force_runs_every_group_in_dependency_order(): void
    {
        $registry = app(
            ErpSyncGroupRegistry::class
        );

        $groups =
            $registry->groups();

        $runner = Mockery::mock(
            ArtisanErpGroupSyncRunner::class
        );

        foreach ($groups as $group) {
            $runner
                ->shouldReceive(
                    'run'
                )
                ->once()
                ->ordered()
                ->with(
                    $group,
                    50,
                    25
                )
                ->andReturn(
                    Command::SUCCESS
                );
        }

        $runner
            ->shouldReceive(
                'output'
            )
            ->times(
                count($groups)
            )
            ->andReturn('');

        $this->app->instance(
            ArtisanErpGroupSyncRunner::class,
            $runner
        );

        $this
            ->artisan(
                'erp:sync:cycle',
                [
                    '--force' =>
                        true,

                    '--per-page' =>
                        50,

                    '--max-pages' =>
                        25,
                ]
            )
            ->assertExitCode(
                Command::SUCCESS
            );
    }

    public function test_cycle_stops_after_first_failed_group(): void
    {
        $groups = app(
            ErpSyncGroupRegistry::class
        )->groups();

        $runner = Mockery::mock(
            ArtisanErpGroupSyncRunner::class
        );

        $runner
            ->shouldReceive('run')
            ->once()
            ->ordered()
            ->with(
                $groups[0],
                100,
                100
            )
            ->andReturn(
                Command::SUCCESS
            );

        $runner
            ->shouldReceive('run')
            ->once()
            ->ordered()
            ->with(
                $groups[1],
                100,
                100
            )
            ->andReturn(
                Command::FAILURE
            );

        $runner
            ->shouldReceive(
                'output'
            )
            ->twice()
            ->andReturn('');

        $this->app->instance(
            ArtisanErpGroupSyncRunner::class,
            $runner
        );

        $this
            ->artisan(
                'erp:sync:cycle',
                [
                    '--force' =>
                        true,
                ]
            )
            ->assertExitCode(
                Command::FAILURE
            );
    }

    public function test_existing_global_lock_skips_cycle_safely(): void
    {
        $runner = Mockery::mock(
            ArtisanErpGroupSyncRunner::class
        );

        $runner->shouldNotReceive(
            'run'
        );

        $this->app->instance(
            ArtisanErpGroupSyncRunner::class,
            $runner
        );

        $lock = Cache::lock(
            'test:erp:sync-cycle',
            300
        );

        $this->assertTrue(
            $lock->get()
        );

        try {
            $this
                ->artisan(
                    'erp:sync:cycle',
                    [
                        '--force' =>
                            true,
                    ]
                )
                ->expectsOutputToContain(
                    'already running'
                )
                ->assertExitCode(
                    Command::SUCCESS
                );
        } finally {
            $lock->release();
        }
    }

    public function test_invalid_page_size_is_rejected(): void
    {
        $runner = Mockery::mock(
            ArtisanErpGroupSyncRunner::class
        );

        $runner->shouldNotReceive(
            'run'
        );

        $this->app->instance(
            ArtisanErpGroupSyncRunner::class,
            $runner
        );

        $this
            ->artisan(
                'erp:sync:cycle',
                [
                    '--force' =>
                        true,

                    '--per-page' =>
                        101,
                ]
            )
            ->assertExitCode(
                Command::INVALID
            );
    }
}