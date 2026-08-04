<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Services\Analytics\MaintenanceKpiService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaintenanceKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $lineId;
    private int $machineId;
    private string $machineExternalId;
    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $machine = Machine::query()
            ->with('productionLine')
            ->orderBy('id')
            ->firstOrFail();

        $line = $machine->productionLine;

        $this->lineId = $line->getKey();
        $this->machineId = $machine->getKey();
        $this->machineExternalId =
            'MACHINE-MAINT-001';

        DB::table('production_lines')
            ->where('id', $this->lineId)
            ->update([
                'source_system' =>
                    'simulated_sage',
                'external_id' =>
                    'LINE-MAINT-001',
            ]);

        DB::table('machines')
            ->where('id', $this->machineId)
            ->update([
                'source_system' =>
                    'simulated_sage',
                'external_id' =>
                    $this->machineExternalId,
            ]);

        $this->batchId =
            $this->createProductionBatch(
                product:
                    Product::query()
                        ->orderBy('id')
                        ->firstOrFail(),

                line:
                    $line
            );
    }

    public function test_it_calculates_supported_maintenance_kpis(): void
    {
        $this->insertDowntime(
            eventNumber: 'DT-PLANNED',
            category: 'planned',
            downtimeType: 'cleaning',
            minutes: 30,
            resolved: true,
        );

        $this->insertDowntime(
            eventNumber: 'DT-UNPLANNED',
            category: 'unplanned',
            downtimeType: 'breakdown',
            minutes: 90,
            resolved: true,
        );

        $this->insertMachineStatus(
            externalId: 'STATUS-RUNNING',
            status: 'running',
            minutes: 600,
            occurredAt: '2026-08-05 06:00:00',
        );

        $this->insertMachineStatus(
            externalId: 'STATUS-FAULT-1',
            status: 'fault',
            minutes: 30,
            occurredAt: '2026-08-05 16:00:00',
        );

        $this->insertMachineStatus(
            externalId: 'STATUS-FAULT-2',
            status: 'fault',
            minutes: 30,
            occurredAt: '2026-08-06 06:00:00',
        );

        $this->insertMaintenance(
            number: 'MNT-CORR-1',
            type: 'corrective',
            status: 'completed',
            minutes: 60,
            startedAt: '2026-08-05 16:00:00',
        );

        $this->insertMaintenance(
            number: 'MNT-CORR-2',
            type: 'corrective',
            status: 'completed',
            minutes: 90,
            startedAt: '2026-08-06 06:00:00',
        );

        $this->insertMaintenance(
            number: 'MNT-PREV-1',
            type: 'preventive',
            status: 'completed',
            minutes: 20,
            startedAt: '2026-08-07 06:00:00',
        );

        $summary = app(
            MaintenanceKpiService::class
        )->summarize(
            $this->filter()
        );

        $this->assertSame(
            120,
            $summary->totalDowntimeMinutes
        );

        $this->assertSame(
            30,
            $summary->plannedDowntimeMinutes
        );

        $this->assertSame(
            90,
            $summary->unplannedDowntimeMinutes
        );

        $this->assertSame(
            660,
            $summary->observedStatusMinutes
        );

        $this->assertSame(
            600,
            $summary->runningMinutes
        );

        $this->assertSame(
            2,
            $summary->faultEventCount
        );

        $this->assertSame(
            90.91,
            $summary->availabilityPercentage
        );

        $this->assertSame(
            75.0,
            $summary->mttrMinutes
        );

        $this->assertSame(
            300.0,
            $summary->mtbfMinutes
        );

        $this->assertSame(
            20.0,
            $summary->failuresPer100RunningHours
        );

        $this->assertSame(
            3,
            $summary->maintenanceInterventionCount
        );

        $this->assertSame(
            1,
            $summary->preventiveInterventionCount
        );

        $this->assertSame(
            2,
            $summary->correctiveInterventionCount
        );

        $this->assertSame(
            1,
            $summary->repeatedFailureMachineCount
        );

        $machine = $summary->machines[0];

        $this->assertTrue(
            $machine->hasRepeatedFailures()
        );

        $this->assertSame(
            $this->machineId,
            $machine->machineId
        );
    }

    public function test_downtime_category_filter_is_applied_without_changing_status_metrics(): void
    {
        $this->insertDowntime(
            eventNumber: 'DT-PLANNED',
            category: 'planned',
            downtimeType: 'changeover',
            minutes: 25,
            resolved: true,
        );

        $this->insertDowntime(
            eventNumber: 'DT-UNPLANNED',
            category: 'unplanned',
            downtimeType: 'breakdown',
            minutes: 75,
            resolved: true,
        );

        $this->insertMachineStatus(
            externalId: 'STATUS-RUNNING',
            status: 'running',
            minutes: 300,
            occurredAt: '2026-08-05 06:00:00',
        );

        $summary = app(
            MaintenanceKpiService::class
        )->summarize(
            $this->filter(
                downtimeCategory:
                    'planned'
            )
        );

        $this->assertSame(
            25,
            $summary->totalDowntimeMinutes
        );

        $this->assertSame(
            25,
            $summary->plannedDowntimeMinutes
        );

        $this->assertSame(
            0,
            $summary->unplannedDowntimeMinutes
        );

        $this->assertSame(
            300,
            $summary->runningMinutes
        );
    }

    public function test_it_recovers_missing_downtime_categories_and_status_durations(): void
    {
        $now = CarbonImmutable::parse(
            '2026-08-05 06:00:00',
            'UTC'
        );

        foreach (
            [
                [
                    'event_number' =>
                        'DT-LEGACY-CLEANING',
                    'title' =>
                        'Downtime - Cleaning',
                    'minutes' => 30,
                    'severity' =>
                        'information',
                ],
                [
                    'event_number' =>
                        'DT-LEGACY-BREAKDOWN',
                    'title' =>
                        'Downtime - Breakdown',
                    'minutes' => 90,
                    'severity' =>
                        'critical',
                ],
            ] as $index => $event
        ) {
            $startedAt = $now->addHours(
                $index
            );

            DB::table('production_events')
                ->insert([
                    'event_number' =>
                        $event['event_number'],
                    'production_batch_id' =>
                        $this->batchId,
                    'production_line_id' =>
                        $this->lineId,
                    'machine_id' =>
                        $this->machineId,
                    'event_type' =>
                        'downtime',
                    'severity' =>
                        $event['severity'],
                    'category' => null,
                    'downtime_type' => null,
                    'title' =>
                        $event['title'],
                    'description' =>
                        'Legacy synchronized downtime.',
                    'started_at' =>
                        $startedAt,
                    'ended_at' =>
                        $startedAt->addMinutes(
                            $event['minutes']
                        ),
                    'duration_minutes' =>
                        $event['minutes'],
                    'is_resolved' => true,
                    'resolved_at' =>
                        $startedAt->addMinutes(
                            $event['minutes']
                        ),
                    'source_system' =>
                        'simulated_sage',
                    'external_id' =>
                        'EXT-'.$event['event_number'],
                    'source_version' => 1,
                    'source_checksum' =>
                        hash(
                            'sha256',
                            $event['event_number']
                        ),
                    'source_updated_at' =>
                        $startedAt,
                    'last_synced_at' =>
                        $startedAt,
                    'import_status' =>
                        'imported',
                    'created_at' =>
                        $startedAt,
                    'updated_at' =>
                        $startedAt,
                ]);
        }

        foreach (
            [
                [
                    'external_id' =>
                        'STATUS-LEGACY-RUNNING-1',
                    'status' => 'running',
                    'occurred_at' =>
                        '2026-08-05 06:00:00',
                ],
                [
                    'external_id' =>
                        'STATUS-LEGACY-FAULT',
                    'status' => 'fault',
                    'occurred_at' =>
                        '2026-08-05 16:00:00',
                ],
                [
                    'external_id' =>
                        'STATUS-LEGACY-RUNNING-2',
                    'status' => 'running',
                    'occurred_at' =>
                        '2026-08-05 16:30:00',
                ],
                [
                    'external_id' =>
                        'STATUS-LEGACY-IDLE',
                    'status' => 'idle',
                    'occurred_at' =>
                        '2026-08-05 18:00:00',
                ],
            ] as $status
        ) {
            $occurredAt = CarbonImmutable::parse(
                $status['occurred_at'],
                'UTC'
            );

            DB::table('machine_status_events')
                ->insert([
                    'external_id' =>
                        $status['external_id'],
                    'machine_external_id' =>
                        $this->machineExternalId,
                    'status' =>
                        $status['status'],
                    'occurred_at' =>
                        $occurredAt,
                    'ended_at' => null,
                    'duration_minutes' => null,
                    'reason' =>
                        'Transition-only source event.',
                    'source_version' => 1,
                    'source_updated_at' =>
                        $occurredAt,
                    'source_checksum' =>
                        hash(
                            'sha256',
                            $status['external_id']
                        ),
                    'last_synced_at' =>
                        $occurredAt,
                    'import_status' =>
                        'imported',
                    'created_at' =>
                        $occurredAt,
                    'updated_at' =>
                        $occurredAt,
                ]);
        }

        $summary = app(
            MaintenanceKpiService::class
        )->summarize(
            new MaintenanceAnalyticsFilter(
                startDate:
                    CarbonImmutable::parse(
                        '2026-08-05',
                        'UTC'
                    ),
                endDate:
                    CarbonImmutable::parse(
                        '2026-08-05',
                        'UTC'
                    ),
                timezone: 'UTC',
                productionLineId:
                    $this->lineId,
                machineId:
                    $this->machineId,
            )
        );

        $this->assertSame(
            30,
            $summary->plannedDowntimeMinutes
        );

        $this->assertSame(
            90,
            $summary->unplannedDowntimeMinutes
        );

        $this->assertSame(
            0,
            $summary->unclassifiedDowntimeMinutes
        );

        $this->assertSame(
            1080,
            $summary->observedStatusMinutes
        );

        $this->assertSame(
            690,
            $summary->runningMinutes
        );

        $this->assertSame(
            1,
            $summary->faultEventCount
        );

        $this->assertSame(
            63.89,
            $summary->availabilityPercentage
        );

        $this->assertSame(
            690.0,
            $summary->mtbfMinutes
        );
    }

    private function filter(
        ?string $downtimeCategory = null
    ): MaintenanceAnalyticsFilter {
        return new MaintenanceAnalyticsFilter(
            startDate:
                CarbonImmutable::parse(
                    '2026-08-01',
                    'UTC'
                ),

            endDate:
                CarbonImmutable::parse(
                    '2026-08-31',
                    'UTC'
                ),

            timezone:
                'UTC',

            productionLineId:
                $this->lineId,

            machineId:
                $this->machineId,

            downtimeCategory:
                $downtimeCategory,
        );
    }

    private function createProductionBatch(
        Product $product,
        ProductionLine $line
    ): int {
        $now = CarbonImmutable::parse(
            '2026-08-05 06:00:00',
            'UTC'
        );

        $orderId = DB::table(
            'production_orders'
        )->insertGetId([
            'order_number' =>
                'PO-MAINT-001',
            'product_id' =>
                $product->getKey(),
            'production_line_id' =>
                $line->getKey(),
            'planned_start_at' =>
                '2026-08-05 06:00:00',
            'planned_end_at' =>
                '2026-08-05 14:00:00',
            'target_quantity' =>
                '1000.000',
            'quantity_unit' =>
                'bottles',
            'status' =>
                'completed',
            'source_system' =>
                'manual',
            'import_status' =>
                'not_applicable',
            'created_at' =>
                $now,
            'updated_at' =>
                $now,
        ]);

        return DB::table(
            'production_batches'
        )->insertGetId([
            'production_order_id' =>
                $orderId,
            'batch_number' =>
                'BAT-MAINT-001',
            'sequence_number' =>
                1,
            'status' =>
                'completed',
            'planned_quantity' =>
                '1000.000',
            'actual_good_quantity' =>
                '980.000',
            'actual_rejected_quantity' =>
                '20.000',
            'quantity_unit' =>
                'bottles',
            'scheduled_start_at' =>
                '2026-08-05 06:00:00',
            'actual_start_at' =>
                '2026-08-05 06:00:00',
            'actual_end_at' =>
                '2026-08-05 14:00:00',
            'source_system' =>
                'manual',
            'import_status' =>
                'not_applicable',
            'created_at' =>
                $now,
            'updated_at' =>
                $now,
        ]);
    }

    private function insertDowntime(
        string $eventNumber,
        string $category,
        string $downtimeType,
        int $minutes,
        bool $resolved
    ): void {
        $startedAt =
            CarbonImmutable::parse(
                '2026-08-05 08:00:00',
                'UTC'
            );

        DB::table('production_events')
            ->insert([
                'event_number' =>
                    $eventNumber,
                'production_batch_id' =>
                    $this->batchId,
                'production_line_id' =>
                    $this->lineId,
                'machine_id' =>
                    $this->machineId,
                'event_type' =>
                    'downtime',
                'severity' =>
                    $category === 'planned'
                        ? 'information'
                        : 'critical',
                'category' =>
                    $category,
                'downtime_type' =>
                    $downtimeType,
                'title' =>
                    'Downtime test',
                'started_at' =>
                    $startedAt,
                'ended_at' =>
                    $startedAt->addMinutes(
                        $minutes
                    ),
                'duration_minutes' =>
                    $minutes,
                'is_resolved' =>
                    $resolved,
                'resolved_at' =>
                    $resolved
                        ? $startedAt->addMinutes(
                            $minutes
                        )
                        : null,
                'source_system' =>
                    'simulated_sage',
                'external_id' =>
                    'EXT-'.$eventNumber,
                'source_version' =>
                    1,
                'source_checksum' =>
                    hash(
                        'sha256',
                        $eventNumber
                    ),
                'source_updated_at' =>
                    $startedAt,
                'last_synced_at' =>
                    $startedAt,
                'import_status' =>
                    'imported',
                'created_at' =>
                    $startedAt,
                'updated_at' =>
                    $startedAt,
            ]);
    }

    private function insertMachineStatus(
        string $externalId,
        string $status,
        int $minutes,
        string $occurredAt
    ): void {
        $start = CarbonImmutable::parse(
            $occurredAt,
            'UTC'
        );

        DB::table('machine_status_events')
            ->insert([
                'external_id' =>
                    $externalId,
                'machine_external_id' =>
                    $this->machineExternalId,
                'status' =>
                    $status,
                'occurred_at' =>
                    $start,
                'ended_at' =>
                    $start->addMinutes(
                        $minutes
                    ),
                'duration_minutes' =>
                    $minutes,
                'reason' =>
                    'Synthetic test status.',
                'source_version' =>
                    1,
                'source_updated_at' =>
                    $start,
                'source_checksum' =>
                    hash(
                        'sha256',
                        $externalId
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
    }

    private function insertMaintenance(
        string $number,
        string $type,
        string $status,
        int $minutes,
        string $startedAt
    ): void {
        $start = CarbonImmutable::parse(
            $startedAt,
            'UTC'
        );

        DB::table('maintenance_history')
            ->insert([
                'external_id' =>
                    'EXT-'.$number,
                'maintenance_number' =>
                    $number,
                'machine_external_id' =>
                    $this->machineExternalId,
                'maintenance_type' =>
                    $type,
                'status' =>
                    $status,
                'scheduled_at' =>
                    $start->subMinutes(10),
                'started_at' =>
                    $start,
                'completed_at' =>
                    $status === 'completed'
                        ? $start->addMinutes(
                            $minutes
                        )
                        : null,
                'description' =>
                    'Synthetic maintenance test.',
                'actions_taken' =>
                    'Inspection and repair.',
                'downtime_minutes' =>
                    $minutes,
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
                        $number
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
    }
}
