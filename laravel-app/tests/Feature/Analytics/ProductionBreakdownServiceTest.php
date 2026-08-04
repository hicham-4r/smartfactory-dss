<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Services\Analytics\ProductionBreakdownService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionBreakdownServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_report_builds_daily_weekly_monthly_and_dimension_breakdowns(): void
    {
        $products = Product::query()
            ->orderBy('product_family_id')
            ->orderBy('id')
            ->get()
            ->unique('product_family_id')
            ->take(2)
            ->values();

        $lines = ProductionLine::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $shifts = Shift::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $this->assertCount(2, $products);
        $this->assertCount(2, $lines);
        $this->assertCount(2, $shifts);

        $this->createFlow(
            suffix: 'BEST',
            date: '2026-07-31',
            productId: $products[0]->getKey(),
            lineId: $lines[0]->getKey(),
            shiftId: $shifts[0]->getKey(),
            targetQuantity: '1000.000',
            producedQuantity: '950.000',
            goodQuantity: '940.000',
            rejectedQuantity: '10.000',
            runtimeMinutes: 420,
            downtimeMinutes: 60,
        );

        $this->createFlow(
            suffix: 'LOWEST',
            date: '2026-08-01',
            productId: $products[1]->getKey(),
            lineId: $lines[1]->getKey(),
            shiftId: $shifts[1]->getKey(),
            targetQuantity: '1000.000',
            producedQuantity: '700.000',
            goodQuantity: '650.000',
            rejectedQuantity: '50.000',
            runtimeMinutes: 300,
            downtimeMinutes: 180,
        );

        $report = app(
            ProductionBreakdownService::class
        )->build(
            new AnalyticsFilter(
                startDate: CarbonImmutable::parse(
                    '2026-07-31',
                    'UTC'
                ),
                endDate: CarbonImmutable::parse(
                    '2026-08-01',
                    'UTC'
                ),
                timezone: 'UTC',
                status: 'completed',
            )
        );

        $this->assertFalse($report->isEmpty());
        $this->assertCount(2, $report->dailyTrend);
        $this->assertCount(1, $report->weeklyTrend);
        $this->assertCount(2, $report->monthlyTrend);
        $this->assertCount(2, $report->byProductionLine);
        $this->assertCount(2, $report->byShift);
        $this->assertCount(2, $report->byProduct);
        $this->assertCount(2, $report->byProductFamily);

        $this->assertSame(
            $lines[0]->name,
            $report->bestLinesByUnit[0]->label
        );

        $this->assertSame(
            $lines[1]->name,
            $report->lowestLinesByUnit[0]->label
        );

        $this->assertSame(
            95.0,
            $report->bestLinesByUnit[0]
                ->achievementPercentage
        );

        $this->assertSame(
            70.0,
            $report->lowestLinesByUnit[0]
                ->achievementPercentage
        );
    }

    public function test_line_filter_is_applied_to_trends_and_every_breakdown(): void
    {
        $product = Product::query()
            ->firstOrFail();

        $lines = ProductionLine::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $shift = Shift::query()
            ->firstOrFail();

        $this->createFlow(
            suffix: 'FILTER-A',
            date: '2026-08-01',
            productId: $product->getKey(),
            lineId: $lines[0]->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '1000.000',
            producedQuantity: '800.000',
            goodQuantity: '790.000',
            rejectedQuantity: '10.000',
            runtimeMinutes: 400,
            downtimeMinutes: 40,
        );

        $this->createFlow(
            suffix: 'FILTER-B',
            date: '2026-08-01',
            productId: $product->getKey(),
            lineId: $lines[1]->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '2000.000',
            producedQuantity: '1800.000',
            goodQuantity: '1750.000',
            rejectedQuantity: '50.000',
            runtimeMinutes: 420,
            downtimeMinutes: 50,
        );

        $report = app(
            ProductionBreakdownService::class
        )->build(
            new AnalyticsFilter(
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
                    $lines[0]->getKey(),
                status: 'completed',
            )
        );

        $this->assertCount(1, $report->byProductionLine);
        $this->assertSame(
            $lines[0]->name,
            $report->byProductionLine[0]->label
        );

        $this->assertCount(1, $report->dailyTrend);
        $this->assertSame(
            '800.000',
            $report->dailyTrend[0]->actualQuantity
        );
        $this->assertSame(
            '1000.000',
            $report->dailyTrend[0]->targetQuantity
        );
    }

    public function test_mixed_units_remain_separate_in_trends_and_rankings(): void
    {
        $product = Product::query()
            ->firstOrFail();

        $lines = ProductionLine::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $shift = Shift::query()
            ->firstOrFail();

        $this->createFlow(
            suffix: 'BOTTLES',
            date: '2026-08-01',
            productId: $product->getKey(),
            lineId: $lines[0]->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '1000.000',
            producedQuantity: '900.000',
            goodQuantity: '890.000',
            rejectedQuantity: '10.000',
            runtimeMinutes: 400,
            downtimeMinutes: 40,
            quantityUnit: 'bottles',
        );

        $this->createFlow(
            suffix: 'LITERS',
            date: '2026-08-01',
            productId: $product->getKey(),
            lineId: $lines[1]->getKey(),
            shiftId: $shift->getKey(),
            targetQuantity: '500.000',
            producedQuantity: '450.000',
            goodQuantity: '445.000',
            rejectedQuantity: '5.000',
            runtimeMinutes: 300,
            downtimeMinutes: 30,
            quantityUnit: 'liters',
        );

        $report = app(
            ProductionBreakdownService::class
        )->build(
            new AnalyticsFilter(
                startDate: CarbonImmutable::parse(
                    '2026-08-01',
                    'UTC'
                ),
                endDate: CarbonImmutable::parse(
                    '2026-08-01',
                    'UTC'
                ),
                timezone: 'UTC',
                status: 'completed',
            )
        );

        $this->assertTrue($report->hasMixedUnits());
        $this->assertCount(2, $report->dailyTrend);
        $this->assertCount(2, $report->bestLinesByUnit);

        $this->assertSame(
            [
                'bottles',
                'liters',
            ],
            array_map(
                static fn ($row): string =>
                    $row->quantityUnit,
                $report->bestLinesByUnit
            )
        );
    }

    private function createFlow(
        string $suffix,
        string $date,
        int $productId,
        int $lineId,
        int $shiftId,
        string $targetQuantity,
        string $producedQuantity,
        string $goodQuantity,
        string $rejectedQuantity,
        int $runtimeMinutes,
        int $downtimeMinutes,
        string $quantityUnit = 'bottles',
    ): void {
        $start = CarbonImmutable::parse(
            $date.' 06:00:00',
            'UTC'
        );

        $end = $start->addHours(8);
        $now = $end;

        $orderId = DB::table(
            'production_orders'
        )->insertGetId([
            'order_number' =>
                'PO-BREAKDOWN-'.$suffix,
            'product_id' => $productId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'planned_start_at' =>
                $start->toDateTimeString(),
            'planned_end_at' =>
                $end->toDateTimeString(),
            'target_quantity' =>
                $targetQuantity,
            'quantity_unit' =>
                $quantityUnit,
            'status' => 'completed',
            'source_system' => 'manual',
            'import_status' =>
                'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $batchId = DB::table(
            'production_batches'
        )->insertGetId([
            'production_order_id' =>
                $orderId,
            'batch_number' =>
                'BAT-BREAKDOWN-'.$suffix,
            'sequence_number' => 1,
            'status' => 'completed',
            'planned_quantity' =>
                $targetQuantity,
            'actual_good_quantity' =>
                $goodQuantity,
            'actual_rejected_quantity' =>
                $rejectedQuantity,
            'quantity_unit' =>
                $quantityUnit,
            'scheduled_start_at' =>
                $start->toDateTimeString(),
            'actual_start_at' =>
                $start->toDateTimeString(),
            'actual_end_at' =>
                $end->toDateTimeString(),
            'source_system' => 'manual',
            'import_status' =>
                'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table(
            'production_records'
        )->insert([
            'record_number' =>
                'PR-BREAKDOWN-'.$suffix,
            'production_batch_id' =>
                $batchId,
            'production_line_id' =>
                $lineId,
            'shift_id' => $shiftId,
            'production_date' => $date,
            'started_at' =>
                $start->toDateTimeString(),
            'ended_at' =>
                $end->toDateTimeString(),
            'produced_quantity' =>
                $producedQuantity,
            'good_quantity' =>
                $goodQuantity,
            'rejected_quantity' =>
                $rejectedQuantity,
            'quantity_unit' =>
                $quantityUnit,
            'runtime_minutes' =>
                $runtimeMinutes,
            'downtime_minutes' =>
                $downtimeMinutes,
            'status' => 'locked',
            'validation_status' =>
                'validated',
            'submitted_at' => $now,
            'locked_at' => $now,
            'source_system' => 'manual',
            'import_status' =>
                'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
