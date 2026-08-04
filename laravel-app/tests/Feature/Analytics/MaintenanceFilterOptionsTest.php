<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\Models\Machine;
use App\Repositories\Contracts\MaintenanceAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_options_include_only_period_backed_lines_and_machines(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $machines = Machine::query()
            ->with('productionLine')
            ->orderBy('id')
            ->get();

        $eligibleMachine = $machines
            ->firstOrFail();

        $irrelevantMachine = $machines
            ->first(
                fn (Machine $machine): bool =>
                    $machine->production_line_id
                    !== $eligibleMachine
                        ->production_line_id
            );

        $this->assertNotNull(
            $irrelevantMachine
        );

        $eligibleExternalId =
            'FILTER-ELIGIBLE-MACHINE';

        DB::table('machines')
            ->where(
                'id',
                $eligibleMachine->getKey()
            )
            ->update([
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    $eligibleExternalId,
            ]);

        DB::table('production_lines')
            ->where(
                'id',
                $eligibleMachine
                    ->production_line_id
            )
            ->update([
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    'FILTER-ELIGIBLE-LINE',
            ]);

        DB::table('machines')
            ->where(
                'id',
                $irrelevantMachine->getKey()
            )
            ->update([
                'source_system' =>
                    'manual',

                'external_id' =>
                    'FILTER-IRRELEVANT-MACHINE',
            ]);

        $start = CarbonImmutable::parse(
            '2026-08-05 06:00:00',
            'UTC'
        );

        DB::table('maintenance_history')
            ->insert([
                'external_id' =>
                    'FILTER-MAINTENANCE-001',

                'maintenance_number' =>
                    'FILTER-MAINTENANCE-001',

                'machine_external_id' =>
                    $eligibleExternalId,

                'maintenance_type' =>
                    'corrective',

                'status' =>
                    'completed',

                'scheduled_at' =>
                    $start->subMinutes(10),

                'started_at' =>
                    $start,

                'completed_at' =>
                    $start->addHour(),

                'description' =>
                    'Period-backed filter fixture.',

                'actions_taken' =>
                    'Inspection and repair.',

                'downtime_minutes' =>
                    60,

                'cost' =>
                    '100.00',

                'currency' =>
                    'MAD',

                'source_version' =>
                    1,

                'source_updated_at' =>
                    $start,

                'source_checksum' =>
                    hash(
                        'sha256',
                        'FILTER-MAINTENANCE-001'
                    ),

                'last_synced_at' =>
                    $start,

                'import_status' =>
                    'imported',

                'created_at' =>
                    $start,

                'updated_at' =>
                    $start,
            ]);

        $repository = app(
            MaintenanceAnalyticsRepositoryInterface::class
        );

        $filter = $this->filter();

        $lines =
            $repository
                ->filterableProductionLines(
                    $filter
                );

        $filterMachines =
            $repository
                ->filterableMachines(
                    $filter
                );

        $this->assertTrue(
            $lines->contains(
                'id',
                $eligibleMachine
                    ->production_line_id
            )
        );

        $this->assertFalse(
            $lines->contains(
                'id',
                $irrelevantMachine
                    ->production_line_id
            )
        );

        $this->assertTrue(
            $filterMachines->contains(
                'id',
                $eligibleMachine->getKey()
            )
        );

        $this->assertFalse(
            $filterMachines->contains(
                'id',
                $irrelevantMachine->getKey()
            )
        );
    }

    public function test_out_of_period_maintenance_does_not_create_filter_options(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $machine = Machine::query()
            ->with('productionLine')
            ->orderBy('id')
            ->firstOrFail();

        $externalId =
            'FILTER-OLD-MACHINE';

        DB::table('machines')
            ->where(
                'id',
                $machine->getKey()
            )
            ->update([
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    $externalId,
            ]);

        $old = CarbonImmutable::parse(
            '2025-01-05 06:00:00',
            'UTC'
        );

        DB::table('maintenance_history')
            ->insert([
                'external_id' =>
                    'FILTER-OLD-MAINTENANCE',

                'maintenance_number' =>
                    'FILTER-OLD-MAINTENANCE',

                'machine_external_id' =>
                    $externalId,

                'maintenance_type' =>
                    'preventive',

                'status' =>
                    'completed',

                'scheduled_at' =>
                    $old,

                'started_at' =>
                    $old,

                'completed_at' =>
                    $old->addHour(),

                'description' =>
                    'Out-of-period fixture.',

                'actions_taken' =>
                    'Inspection.',

                'downtime_minutes' =>
                    60,

                'cost' =>
                    '50.00',

                'currency' =>
                    'MAD',

                'source_version' =>
                    1,

                'source_updated_at' =>
                    $old,

                'source_checksum' =>
                    hash(
                        'sha256',
                        'FILTER-OLD-MAINTENANCE'
                    ),

                'last_synced_at' =>
                    $old,

                'import_status' =>
                    'imported',

                'created_at' =>
                    $old,

                'updated_at' =>
                    $old,
            ]);

        $repository = app(
            MaintenanceAnalyticsRepositoryInterface::class
        );

        $this->assertFalse(
            $repository
                ->filterableProductionLines(
                    $this->filter()
                )
                ->contains(
                    'id',
                    $machine
                        ->production_line_id
                )
        );

        $this->assertFalse(
            $repository
                ->filterableMachines(
                    $this->filter()
                )
                ->contains(
                    'id',
                    $machine->getKey()
                )
        );
    }

    private function filter(): MaintenanceAnalyticsFilter
    {
        return new MaintenanceAnalyticsFilter(
            startDate:
                CarbonImmutable::parse(
                    '2026-08-01',
                    'UTC'
                ),

            endDate:
                CarbonImmutable::parse(
                    '2026-08-15',
                    'UTC'
                ),

            timezone:
                'UTC',
        );
    }
}
