<?php

namespace Tests\Feature\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Services\Dashboard\ProductionManagerDashboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionManagerDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductionMasterDataSeeder::class);
    }

    public function test_manager_snapshot_reuses_verified_analytics_and_exposes_executive_exceptions(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createExecutiveFlow(
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
        );

        $snapshot = app(
            ProductionManagerDashboardService::class
        )->build(
            new DashboardFilter(
                startDate: CarbonImmutable::parse('2026-08-01', 'UTC'),
                endDate: CarbonImmutable::parse('2026-08-01', 'UTC'),
                timezone: 'UTC',
                productionLineId: $line->getKey(),
                productId: $product->getKey(),
                shiftId: $shift->getKey(),
            )
        );

        $this->assertSame(0, $snapshot->inProgressOrderCount);
        $this->assertSame(1, $snapshot->completedOrderCount);
        $this->assertSame(1, $snapshot->criticalEventCount);
        $this->assertCount(1, $snapshot->criticalEvents);
        $this->assertSame(
            '600.000',
            $snapshot->production->primaryUnit()?->actualQuantity
        );
        $this->assertNotEmpty($snapshot->breakdowns->monthlyTrend);
        $this->assertNotEmpty($snapshot->breakdowns->byProductionLine);
        $this->assertSame(1, $snapshot->quality->failedInspectionCount);
        $this->assertSame(1, $snapshot->quality->blockedLotCount);
        $this->assertSame(
            1,
            $snapshot->quality->criticalNonconformityCount
        );
        $this->assertTrue($snapshot->needsAttention());
        $this->assertTrue(
            collect($snapshot->productionLines)->contains(
                fn ($option): bool =>
                    $option->id === $line->getKey()
            )
        );
    }

    private function createExecutiveFlow(
        int $productId,
        int $lineId,
        int $shiftId,
    ): void {
        $now = CarbonImmutable::parse(
            '2026-08-01 12:00:00',
            'UTC'
        );
        $batchExternalId = 'DASH-MGR-BATCH-001';

        $orderId = DB::table('production_orders')->insertGetId([
            'order_number' => 'PO-DASH-MGR-001',
            'product_id' => $productId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'planned_start_at' => '2026-08-01 06:00:00',
            'planned_end_at' => '2026-08-01 14:00:00',
            'target_quantity' => '1000.000',
            'quantity_unit' => 'bottles',
            'priority' => 1,
            'status' => 'completed',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $batchId = DB::table('production_batches')->insertGetId([
            'external_id' => $batchExternalId,
            'production_order_id' => $orderId,
            'batch_number' => 'BAT-DASH-MGR-001',
            'sequence_number' => 1,
            'status' => 'completed',
            'planned_quantity' => '1000.000',
            'actual_good_quantity' => '590.000',
            'actual_rejected_quantity' => '10.000',
            'quantity_unit' => 'bottles',
            'scheduled_start_at' => '2026-08-01 06:00:00',
            'actual_start_at' => '2026-08-01 06:00:00',
            'actual_end_at' => '2026-08-01 14:00:00',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $recordId = DB::table('production_records')->insertGetId([
            'record_number' => 'PR-DASH-MGR-001',
            'production_batch_id' => $batchId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'production_date' => '2026-08-01',
            'started_at' => '2026-08-01 06:00:00',
            'ended_at' => '2026-08-01 14:00:00',
            'produced_quantity' => '600.000',
            'good_quantity' => '590.000',
            'rejected_quantity' => '10.000',
            'quantity_unit' => 'bottles',
            'runtime_minutes' => 420,
            'downtime_minutes' => 60,
            'status' => 'locked',
            'validation_status' => 'validated',
            'submitted_at' => $now,
            'locked_at' => $now,
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('production_events')->insert([
            'event_number' => 'EVT-DASH-MGR-001',
            'production_batch_id' => $batchId,
            'production_record_id' => $recordId,
            'production_line_id' => $lineId,
            'machine_id' => null,
            'shift_id' => $shiftId,
            'operator_id' => null,
            'event_type' => 'downtime',
            'severity' => 'critical',
            'title' => 'Critical executive event',
            'description' => 'Synthetic unresolved manager event.',
            'started_at' => '2026-08-01 09:00:00',
            'ended_at' => null,
            'duration_minutes' => 60,
            'is_resolved' => false,
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('finished_lots')->insert([
            'external_id' => 'DASH-MGR-LOT-001',
            'lot_number' => 'LOT-DASH-MGR-001',
            'batch_external_id' => $batchExternalId,
            'product_external_id' => 'DASH-MGR-PRODUCT-001',
            'status' => 'blocked',
            'produced_at' => '2026-08-01 14:30:00',
            'produced_quantity' => '600.000',
            'released_quantity' => '0.000',
            'rejected_quantity' => '10.000',
            'quantity_unit' => 'bottles',
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('inspections')->insert([
            'external_id' => 'DASH-MGR-INS-001',
            'inspection_number' => 'INS-DASH-MGR-001',
            'batch_external_id' => $batchExternalId,
            'finished_lot_external_id' => 'DASH-MGR-LOT-001',
            'inspection_type' => 'final_release',
            'result' => 'failed',
            'inspected_at' => '2026-08-01 15:00:00',
            'sample_size' => 0,
            'passed_quantity' => 0,
            'failed_quantity' => 0,
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('nonconformities')->insert([
            'external_id' => 'DASH-MGR-NC-001',
            'nonconformity_number' => 'NC-DASH-MGR-001',
            'inspection_external_id' => 'DASH-MGR-INS-001',
            'batch_external_id' => $batchExternalId,
            'severity' => 'critical',
            'status' => 'open',
            'category' => 'packaging',
            'description' => 'Synthetic critical nonconformity.',
            'detected_at' => '2026-08-01 15:10:00',
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
