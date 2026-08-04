<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Services\Analytics\ProductionKpiService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_summary_uses_only_validated_non_cancelled_records(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createFlow(
            suffix: 'PRIMARY',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '1000.000',
            producedQuantity: '900.000',
            goodQuantity: '870.000',
            rejectedQuantity: '30.000',
            runtimeMinutes: 420,
            downtimeMinutes: 60,
            orderStatus: 'completed',
            validationStatus: 'validated',
        );

        $this->createFlow(
            suffix: 'PENDING',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '500.000',
            producedQuantity: '500.000',
            goodQuantity: '490.000',
            rejectedQuantity: '10.000',
            runtimeMinutes: 200,
            downtimeMinutes: 20,
            orderStatus: 'completed',
            validationStatus: 'pending',
        );

        $this->createFlow(
            suffix: 'CANCELLED',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '300.000',
            producedQuantity: '300.000',
            goodQuantity: '300.000',
            rejectedQuantity: '0.000',
            runtimeMinutes: 100,
            downtimeMinutes: 0,
            orderStatus: 'cancelled',
            validationStatus: 'validated',
        );

        $summary = app(
            ProductionKpiService::class
        )->summarize(
            $this->filter()
        );

        $this->assertFalse($summary->isEmpty());
        $this->assertFalse($summary->hasMixedUnits());
        $this->assertSame(1, $summary->validatedRecordCount);
        $this->assertSame(2, $summary->targetOrderCount);

        $unit = $summary->primaryUnit();

        $this->assertNotNull($unit);
        $this->assertSame('bottles', $unit->quantityUnit);
        $this->assertSame('1500.000', $unit->targetQuantity);
        $this->assertSame('900.000', $unit->actualQuantity);
        $this->assertSame('870.000', $unit->goodQuantity);
        $this->assertSame('30.000', $unit->rejectedQuantity);
        $this->assertSame(60.0, $unit->achievementPercentage);
        $this->assertSame(3.33, $unit->rejectionPercentage);
        $this->assertSame('128.571', $unit->averageProductionRatePerHour);
        $this->assertSame(87.5, $unit->observedUtilizationPercentage);
        $this->assertSame(420, $unit->runtimeMinutes);
        $this->assertSame(60, $unit->downtimeMinutes);
    }


    public function test_empty_period_returns_explicit_empty_summary(): void
    {
        $summary = app(
            ProductionKpiService::class
        )->summarize(
            new AnalyticsFilter(
                startDate: CarbonImmutable::parse(
                    '2026-09-01',
                    'UTC'
                ),
                endDate: CarbonImmutable::parse(
                    '2026-09-30',
                    'UTC'
                ),
                timezone: 'UTC',
            )
        );

        $this->assertTrue($summary->isEmpty());
        $this->assertNull($summary->primaryUnit());
        $this->assertSame(0, $summary->validatedRecordCount);
        $this->assertSame(0, $summary->targetOrderCount);
    }

    public function test_mixed_quantity_units_are_not_combined(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createFlow(
            suffix: 'BOTTLES',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '1000.000',
            producedQuantity: '900.000',
            goodQuantity: '890.000',
            rejectedQuantity: '10.000',
            runtimeMinutes: 400,
            downtimeMinutes: 40,
            orderStatus: 'completed',
            validationStatus: 'validated',
            quantityUnit: 'bottles',
        );

        $this->createFlow(
            suffix: 'LITERS',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '500.000',
            producedQuantity: '450.000',
            goodQuantity: '445.000',
            rejectedQuantity: '5.000',
            runtimeMinutes: 300,
            downtimeMinutes: 30,
            orderStatus: 'completed',
            validationStatus: 'validated',
            quantityUnit: 'liters',
        );

        $summary = app(
            ProductionKpiService::class
        )->summarize(
            $this->filter()
        );

        $this->assertTrue($summary->hasMixedUnits());
        $this->assertNull($summary->primaryUnit());
        $this->assertCount(2, $summary->units);
        $this->assertSame(
            [
                'bottles',
                'liters',
            ],
            array_map(
                static fn ($unit): string =>
                    $unit->quantityUnit,
                $summary->units
            )
        );
    }

    public function test_zero_denominators_return_not_applicable(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createFlow(
            suffix: 'ZERO',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '0.000',
            producedQuantity: '0.000',
            goodQuantity: '0.000',
            rejectedQuantity: '0.000',
            runtimeMinutes: 0,
            downtimeMinutes: 0,
            orderStatus: 'completed',
            validationStatus: 'validated',
        );

        $unit = app(
            ProductionKpiService::class
        )
            ->summarize($this->filter())
            ->primaryUnit();

        $this->assertNotNull($unit);
        $this->assertNull($unit->achievementPercentage);
        $this->assertNull($unit->rejectionPercentage);
        $this->assertNull($unit->averageProductionRatePerHour);
        $this->assertNull($unit->observedUtilizationPercentage);
    }

    public function test_production_line_filter_is_applied_to_targets_and_actuals(): void
    {
        $product = Product::query()->firstOrFail();
        $lines = ProductionLine::query()
            ->orderBy('id')
            ->limit(2)
            ->get();
        $shift = Shift::query()->firstOrFail();

        $this->assertCount(2, $lines);

        foreach ($lines as $index => $line) {
            $this->createFlow(
                suffix: 'LINE'.($index + 1),
                productId: $product->getKey(),
                lineId: $line->getKey(),
                shiftId: $shift->getKey(),
                targetQuantity: $index === 0
                    ? '1000.000'
                    : '2000.000',
                producedQuantity: $index === 0
                    ? '800.000'
                    : '1800.000',
                goodQuantity: $index === 0
                    ? '790.000'
                    : '1750.000',
                rejectedQuantity: $index === 0
                    ? '10.000'
                    : '50.000',
                runtimeMinutes: 400,
                downtimeMinutes: 40,
                orderStatus: 'completed',
                validationStatus: 'validated',
            );
        }

        $filter = new AnalyticsFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            timezone: 'UTC',
            productionLineId:
                $lines->first()->getKey(),
        );

        $unit = app(
            ProductionKpiService::class
        )
            ->summarize($filter)
            ->primaryUnit();

        $this->assertNotNull($unit);
        $this->assertSame('1000.000', $unit->targetQuantity);
        $this->assertSame('800.000', $unit->actualQuantity);
        $this->assertSame(80.0, $unit->achievementPercentage);
    }

    private function filter(): AnalyticsFilter
    {
        return new AnalyticsFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-01',
                'UTC'
            ),
            timezone: 'UTC',
        );
    }

    private function createFlow(
        string $suffix,
        int $productId,
        int $lineId,
        int $shiftId,
        string $targetQuantity,
        string $producedQuantity,
        string $goodQuantity,
        string $rejectedQuantity,
        int $runtimeMinutes,
        int $downtimeMinutes,
        string $orderStatus,
        string $validationStatus,
        string $quantityUnit = 'bottles',
    ): void {
        $now = CarbonImmutable::parse(
            '2026-08-01 12:00:00',
            'UTC'
        );

        $orderId = DB::table(
            'production_orders'
        )->insertGetId([
            'order_number' => 'PO-KPI-'.$suffix,
            'product_id' => $productId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'planned_start_at' =>
                '2026-08-01 06:00:00',
            'planned_end_at' =>
                '2026-08-01 14:00:00',
            'target_quantity' => $targetQuantity,
            'quantity_unit' => $quantityUnit,
            'status' => $orderStatus,
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $batchId = DB::table(
            'production_batches'
        )->insertGetId([
            'production_order_id' => $orderId,
            'batch_number' => 'BAT-KPI-'.$suffix,
            'sequence_number' => 1,
            'status' => 'completed',
            'planned_quantity' => $targetQuantity,
            'actual_good_quantity' => $goodQuantity,
            'actual_rejected_quantity' => $rejectedQuantity,
            'quantity_unit' => $quantityUnit,
            'scheduled_start_at' =>
                '2026-08-01 06:00:00',
            'actual_start_at' =>
                '2026-08-01 06:00:00',
            'actual_end_at' =>
                '2026-08-01 14:00:00',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('production_records')->insert([
            'record_number' => 'PR-KPI-'.$suffix,
            'production_batch_id' => $batchId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'production_date' => '2026-08-01',
            'started_at' => '2026-08-01 06:00:00',
            'ended_at' => '2026-08-01 14:00:00',
            'produced_quantity' => $producedQuantity,
            'good_quantity' => $goodQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'quantity_unit' => $quantityUnit,
            'runtime_minutes' => $runtimeMinutes,
            'downtime_minutes' => $downtimeMinutes,
            'status' => $validationStatus === 'validated'
                ? 'locked'
                : 'submitted',
            'validation_status' => $validationStatus,
            'submitted_at' => $now,
            'locked_at' => $validationStatus === 'validated'
                ? $now
                : null,
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
