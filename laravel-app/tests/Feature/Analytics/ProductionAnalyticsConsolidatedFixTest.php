<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\Http\Requests\Analytics\BrowseProductionKpiRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use App\Services\Analytics\ProductionKpiService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductionAnalyticsConsolidatedFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_data_backed_filter_options_merge_exact_duplicates_and_exclude_unused_catalogue_rows(): void
    {
        $family = ProductFamily::query()
            ->orderBy('id')
            ->firstOrFail();

        $product = Product::query()
            ->where('product_family_id', $family->getKey())
            ->orderBy('id')
            ->firstOrFail();

        $line = ProductionLine::query()
            ->orderBy('id')
            ->firstOrFail();

        $shift = Shift::query()
            ->orderBy('id')
            ->firstOrFail();

        $duplicateFamily = ProductFamily::create([
            'code' => 'DUP-FAMILY',
            'name' => strtoupper($family->name),
            'description' => 'Duplicate fixture.',
            'is_active' => true,
        ]);

        $duplicateProduct = Product::create([
            'product_family_id' => $duplicateFamily->getKey(),
            'code' => 'DUP-PRODUCT',
            'sku' => 'DUP-PRODUCT-SKU',
            'name' => strtoupper($product->name),
            'base_unit' => $product->base_unit,
            'package_format' => $product->package_format,
            'nominal_volume' => $product->nominal_volume,
            'is_active' => true,
        ]);

        $duplicateLine = ProductionLine::create([
            'code' => 'DUP-LINE',
            'name' => strtoupper($line->name),
            'plant_area' => $line->plant_area,
            'description' => 'Duplicate fixture.',
            'nominal_capacity_per_hour' =>
                $line->nominal_capacity_per_hour,
            'capacity_unit' => $line->capacity_unit,
            'is_active' => true,
        ]);

        $duplicateShift = Shift::create([
            'code' => 'DUP-SHIFT',
            'name' => strtoupper($shift->name),
            'starts_at' => $shift->starts_at,
            'ends_at' => $shift->ends_at,
            'crosses_midnight' => $shift->crosses_midnight,
            'is_active' => true,
        ]);

        $this->createFlow(
            suffix: 'ORIGINAL',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            orderStatus: 'completed',
            validationStatus: 'validated'
        );

        $this->createFlow(
            suffix: 'DUPLICATE',
            productId: $duplicateProduct->getKey(),
            lineId: $duplicateLine->getKey(),
            shiftId: $duplicateShift->getKey(),
            orderStatus: 'completed',
            validationStatus: 'validated'
        );

        $repository = app(
            ProductionAnalyticsRepositoryInterface::class
        );

        $filter = $this->filter();

        $families = $repository
            ->filterableProductFamilies($filter);
        $products = $repository
            ->filterableProducts($filter);
        $lines = $repository
            ->filterableProductionLines($filter);
        $shifts = $repository
            ->filterableShifts($filter);

        $this->assertCount(
            1,
            $families->filter(
                fn (object $item): bool =>
                    $this->normalized($item->name)
                    === $this->normalized($family->name)
            )
        );

        $this->assertCount(
            1,
            $products->filter(
                fn (object $item): bool =>
                    $this->normalized($item->name)
                    === $this->normalized($product->name)
            )
        );

        $this->assertCount(
            1,
            $lines->filter(
                fn (object $item): bool =>
                    $this->normalized($item->name)
                    === $this->normalized($line->name)
            )
        );

        $this->assertCount(
            1,
            $shifts->filter(
                fn (object $item): bool =>
                    $this->normalized($item->name)
                    === $this->normalized($shift->name)
            )
        );

        $unusedLine = ProductionLine::query()
            ->whereNotIn(
                'id',
                [
                    $line->getKey(),
                    $duplicateLine->getKey(),
                ]
            )
            ->firstOrFail();

        $this->assertFalse(
            $lines->contains(
                fn (object $item): bool =>
                    (int) $item->id
                    === $unusedLine->getKey()
            )
        );
    }

    public function test_in_progress_status_includes_pending_records_as_provisional_kpis(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createFlow(
            suffix: 'IN-PROGRESS',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            orderStatus: 'in_progress',
            validationStatus: 'pending',
            targetQuantity: '1000.000',
            producedQuantity: '600.000',
            goodQuantity: '585.000',
            rejectedQuantity: '15.000',
            runtimeMinutes: 260,
            downtimeMinutes: 40,
        );

        $summary = app(
            ProductionKpiService::class
        )->summarize(
            $this->filter(status: 'in_progress')
        );

        $unit = $summary->primaryUnit();

        $this->assertNotNull($unit);
        $this->assertTrue($summary->isProvisional());
        $this->assertSame(1, $summary->recordCount);
        $this->assertSame(0, $summary->validatedRecordCount);
        $this->assertSame(1, $summary->provisionalRecordCount);
        $this->assertSame('600.000', $unit->actualQuantity);
        $this->assertSame('585.000', $unit->goodQuantity);
        $this->assertSame('15.000', $unit->rejectedQuantity);
        $this->assertSame(60.0, $unit->achievementPercentage);
        $this->assertSame(260, $unit->runtimeMinutes);
        $this->assertSame(40, $unit->downtimeMinutes);
    }

    public function test_shift_specific_summary_uses_batch_target_when_order_shift_is_null(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createFlow(
            suffix: 'SHIFT-TARGET',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            orderStatus: 'completed',
            validationStatus: 'validated',
            targetQuantity: '1200.000',
            producedQuantity: '1080.000',
            goodQuantity: '1060.000',
            rejectedQuantity: '20.000',
            nullOrderShift: true,
        );

        $summary = app(
            ProductionKpiService::class
        )->summarize(
            $this->filter(
                status: 'completed',
                shiftId: $shift->getKey()
            )
        );

        $unit = $summary->primaryUnit();

        $this->assertNotNull($unit);
        $this->assertSame(1, $summary->targetOrderCount);
        $this->assertSame('1200.000', $unit->targetQuantity);
        $this->assertSame('1080.000', $unit->actualQuantity);
        $this->assertSame(90.0, $unit->achievementPercentage);
    }

    public function test_request_accepts_only_execution_statuses(): void
    {
        $base = [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-01',
            'timezone' => 'UTC',
        ];

        $request = new BrowseProductionKpiRequest();

        foreach (['in_progress', 'completed', null] as $status) {
            $validator = Validator::make(
                [...$base, 'status' => $status],
                $request->rules()
            );

            $this->assertFalse(
                $validator->fails(),
                'Execution status should be accepted.'
            );
        }

        foreach (['draft', 'planned', 'released', 'cancelled'] as $status) {
            $validator = Validator::make(
                [...$base, 'status' => $status],
                $request->rules()
            );

            $this->assertTrue(
                $validator->fails(),
                "Status [{$status}] should be rejected by the execution KPI request."
            );
        }
    }

    public function test_completed_status_still_excludes_pending_records(): void
    {
        $product = Product::query()->firstOrFail();
        $line = ProductionLine::query()->firstOrFail();
        $shift = Shift::query()->firstOrFail();

        $this->createFlow(
            suffix: 'COMPLETED-VALIDATED',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            orderStatus: 'completed',
            validationStatus: 'validated',
            producedQuantity: '900.000'
        );

        $this->createFlow(
            suffix: 'COMPLETED-PENDING',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            shiftId: $shift->getKey(),
            orderStatus: 'completed',
            validationStatus: 'pending',
            producedQuantity: '500.000'
        );

        $summary = app(
            ProductionKpiService::class
        )->summarize(
            $this->filter(status: 'completed')
        );

        $this->assertFalse($summary->isProvisional());
        $this->assertSame(1, $summary->recordCount);
        $this->assertSame(1, $summary->validatedRecordCount);
        $this->assertSame(0, $summary->provisionalRecordCount);
        $this->assertSame(
            '900.000',
            $summary->primaryUnit()?->actualQuantity
        );
    }

    private function filter(
        ?string $status = null,
        ?int $shiftId = null,
    ): AnalyticsFilter {
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
            shiftId: $shiftId,
            status: $status,
        );
    }

    private function createOrderOnly(
        string $suffix,
        int $productId,
        int $lineId,
        ?int $shiftId,
        string $status,
        string $targetQuantity,
    ): int {
        $now = CarbonImmutable::parse(
            '2026-08-01 12:00:00',
            'UTC'
        );

        return DB::table('production_orders')
            ->insertGetId([
                'order_number' => 'PO-CONSOLIDATED-'.$suffix,
                'product_id' => $productId,
                'production_line_id' => $lineId,
                'shift_id' => $shiftId,
                'planned_start_at' =>
                    '2026-08-01 06:00:00',
                'planned_end_at' =>
                    '2026-08-01 14:00:00',
                'target_quantity' => $targetQuantity,
                'quantity_unit' => 'bottles',
                'status' => $status,
                'source_system' => 'manual',
                'import_status' => 'not_applicable',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function createFlow(
        string $suffix,
        int $productId,
        int $lineId,
        int $shiftId,
        string $orderStatus,
        string $validationStatus,
        string $targetQuantity = '1000.000',
        string $producedQuantity = '900.000',
        string $goodQuantity = '890.000',
        string $rejectedQuantity = '10.000',
        int $runtimeMinutes = 420,
        int $downtimeMinutes = 60,
        bool $nullOrderShift = false,
    ): void {
        $now = CarbonImmutable::parse(
            '2026-08-01 12:00:00',
            'UTC'
        );

        $orderId = $this->createOrderOnly(
            suffix: $suffix,
            productId: $productId,
            lineId: $lineId,
            shiftId: $nullOrderShift ? null : $shiftId,
            status: $orderStatus,
            targetQuantity: $targetQuantity,
        );

        $batchId = DB::table('production_batches')
            ->insertGetId([
                'production_order_id' => $orderId,
                'batch_number' => 'BAT-CONSOLIDATED-'.$suffix,
                'sequence_number' => 1,
                'status' => $orderStatus === 'completed'
                    ? 'completed'
                    : 'in_progress',
                'planned_quantity' => $targetQuantity,
                'actual_good_quantity' => $goodQuantity,
                'actual_rejected_quantity' => $rejectedQuantity,
                'quantity_unit' => 'bottles',
                'scheduled_start_at' =>
                    '2026-08-01 06:00:00',
                'actual_start_at' =>
                    '2026-08-01 06:00:00',
                'actual_end_at' => $orderStatus === 'completed'
                    ? '2026-08-01 14:00:00'
                    : null,
                'source_system' => 'manual',
                'import_status' => 'not_applicable',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('production_records')->insert([
            'record_number' => 'PR-CONSOLIDATED-'.$suffix,
            'production_batch_id' => $batchId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'production_date' => '2026-08-01',
            'started_at' => '2026-08-01 06:00:00',
            'ended_at' => $orderStatus === 'completed'
                ? '2026-08-01 14:00:00'
                : null,
            'produced_quantity' => $producedQuantity,
            'good_quantity' => $goodQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'quantity_unit' => 'bottles',
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

    private function normalized(
        mixed $value
    ): string {
        return mb_strtolower(trim((string) $value));
    }
}
