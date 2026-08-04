<?php

namespace Tests\Feature\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use App\Models\Machine;
use App\Services\Dashboard\MaintenanceManagerDashboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceManagerDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_reuses_maintenance_kpis_and_data_backed_options(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $machine = Machine::query()
            ->with('productionLine')
            ->orderBy('id')
            ->firstOrFail();

        $machineExternalId =
            'DASH-MAINT-MACHINE-001';

        DB::table('machines')
            ->where('id', $machine->getKey())
            ->update([
                'source_system' =>
                    'simulated_sage',
                'external_id' =>
                    $machineExternalId,
            ]);

        $start = CarbonImmutable::parse(
            '2026-08-05 06:00:00',
            'UTC'
        );

        DB::table('machine_status_events')
            ->insert([
                'external_id' =>
                    'DASH-MAINT-STATUS-001',
                'machine_external_id' =>
                    $machineExternalId,
                'status' => 'running',
                'occurred_at' => $start,
                'ended_at' =>
                    $start->addMinutes(120),
                'duration_minutes' => 120,
                'reason' =>
                    'Dashboard test running state.',
                'source_version' => 1,
                'source_updated_at' => $start,
                'source_checksum' =>
                    hash(
                        'sha256',
                        'DASH-MAINT-STATUS-001'
                    ),
                'last_synced_at' => $start,
                'import_status' => 'imported',
                'created_at' => $start,
                'updated_at' => $start,
            ]);

        DB::table('maintenance_history')
            ->insert([
                'external_id' =>
                    'DASH-MAINT-HISTORY-001',
                'maintenance_number' =>
                    'DASH-MAINT-HISTORY-001',
                'machine_external_id' =>
                    $machineExternalId,
                'maintenance_type' =>
                    'corrective',
                'status' => 'completed',
                'scheduled_at' =>
                    $start->subMinutes(10),
                'started_at' => $start,
                'completed_at' =>
                    $start->addMinutes(45),
                'description' =>
                    'Dashboard maintenance fixture.',
                'actions_taken' =>
                    'Inspection and repair.',
                'downtime_minutes' => 45,
                'cost' => '100.00',
                'currency' => 'MAD',
                'source_version' => 1,
                'source_updated_at' => $start,
                'source_checksum' =>
                    hash(
                        'sha256',
                        'DASH-MAINT-HISTORY-001'
                    ),
                'last_synced_at' => $start,
                'import_status' => 'imported',
                'created_at' => $start,
                'updated_at' => $start,
            ]);

        $snapshot = app(
            MaintenanceManagerDashboardService::class
        )->build(
            new DashboardFilter(
                startDate:
                    CarbonImmutable::parse(
                        '2026-08-01',
                        'UTC'
                    ),
                endDate:
                    CarbonImmutable::parse(
                        '2026-08-10',
                        'UTC'
                    ),
                timezone: 'UTC',
            )
        );

        $this->assertSame(
            1,
            $snapshot
                ->maintenance
                ->maintenanceInterventionCount
        );

        $this->assertSame(
            120,
            $snapshot
                ->maintenance
                ->runningMinutes
        );

        $this->assertSame(
            100.0,
            $snapshot
                ->maintenance
                ->availabilityPercentage
        );

        $this->assertTrue(
            collect(
                $snapshot->productionLines
            )->contains(
                fn ($option): bool =>
                    $option->id
                    === $machine
                        ->production_line_id
            )
        );

        $this->assertTrue(
            collect(
                $snapshot->machines
            )->contains(
                fn ($option): bool =>
                    $option->id
                    === $machine->getKey()
            )
        );
    }

    public function test_selected_machine_filter_is_applied(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $machine = Machine::query()
            ->orderBy('id')
            ->firstOrFail();

        $snapshot = app(
            MaintenanceManagerDashboardService::class
        )->build(
            new DashboardFilter(
                startDate:
                    CarbonImmutable::parse(
                        '2026-08-01',
                        'UTC'
                    ),
                endDate:
                    CarbonImmutable::parse(
                        '2026-08-10',
                        'UTC'
                    ),
                timezone: 'UTC',
                productionLineId:
                    $machine->production_line_id,
                machineId:
                    $machine->getKey(),
            )
        );

        $this->assertSame(
            $machine->getKey(),
            $snapshot->filter->machineId
        );

        $this->assertSame(
            $machine->production_line_id,
            $snapshot
                ->filter
                ->productionLineId
        );
    }
}
