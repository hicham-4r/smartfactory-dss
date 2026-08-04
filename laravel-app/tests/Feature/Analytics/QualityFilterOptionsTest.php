<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Repositories\Contracts\QualityAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QualityFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductionMasterDataSeeder::class);
    }

    public function test_filter_options_are_backed_by_period_quality_data(): void
    {
        $product = Product::query()->orderBy('id')->firstOrFail();
        $line = ProductionLine::query()->orderBy('id')->firstOrFail();
        $this->createFlow($product->getKey(), $line->getKey());

        $repository = app(QualityAnalyticsRepositoryInterface::class);
        $filter = new QualityAnalyticsFilter(
            startDate: CarbonImmutable::parse('2026-08-01', 'UTC'),
            endDate: CarbonImmutable::parse('2026-08-01', 'UTC'),
            timezone: 'UTC',
        );

        $lines = $repository->filterableProductionLines($filter);
        $products = $repository->filterableProducts($filter);
        $families = $repository->filterableProductFamilies($filter);

        $this->assertCount(1, $lines);
        $this->assertSame($line->getKey(), (int) $lines->first()->id);
        $this->assertCount(1, $products);
        $this->assertSame($product->getKey(), (int) $products->first()->id);
        $this->assertCount(1, $families);
        $this->assertSame(
            $product->product_family_id,
            (int) $families->first()->id
        );

        $outside = new QualityAnalyticsFilter(
            startDate: CarbonImmutable::parse('2026-09-01', 'UTC'),
            endDate: CarbonImmutable::parse('2026-09-30', 'UTC'),
            timezone: 'UTC',
        );

        $this->assertTrue(
            $repository->filterableProductionLines($outside)->isEmpty()
        );
    }

    private function createFlow(int $productId, int $lineId): void
    {
        $now = CarbonImmutable::parse('2026-08-01 12:00:00', 'UTC');
        $orderId = DB::table('production_orders')->insertGetId([
            'order_number' => 'QUALITY-FILTER-ORDER',
            'product_id' => $productId,
            'production_line_id' => $lineId,
            'shift_id' => null,
            'planned_start_at' => '2026-08-01 06:00:00',
            'planned_end_at' => '2026-08-01 14:00:00',
            'target_quantity' => '1000.000',
            'quantity_unit' => 'bottles',
            'status' => 'completed',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('production_batches')->insert([
            'external_id' => 'QUALITY-FILTER-BATCH',
            'production_order_id' => $orderId,
            'batch_number' => 'QUALITY-FILTER-BATCH-NUMBER',
            'sequence_number' => 1,
            'status' => 'completed',
            'planned_quantity' => '1000.000',
            'actual_good_quantity' => '990.000',
            'actual_rejected_quantity' => '10.000',
            'quantity_unit' => 'bottles',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('inspections')->insert([
            'external_id' => 'QUALITY-FILTER-INSPECTION',
            'inspection_number' => 'QUALITY-FILTER-INSPECTION-NUMBER',
            'batch_external_id' => 'QUALITY-FILTER-BATCH',
            'inspection_type' => 'final_release',
            'result' => 'passed',
            'inspected_at' => '2026-08-01 15:00:00',
            'sample_size' => 100,
            'passed_quantity' => 100,
            'failed_quantity' => 0,
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
