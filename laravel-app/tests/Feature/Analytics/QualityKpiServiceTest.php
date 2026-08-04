<?php

namespace Tests\Feature\Analytics;

use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Services\Analytics\QualityKpiService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QualityKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductionMasterDataSeeder::class);
    }

    public function test_quality_summary_uses_synchronized_quality_tables(): void
    {
        $product = Product::query()->orderBy('id')->firstOrFail();
        $line = ProductionLine::query()->orderBy('id')->firstOrFail();

        $this->createQualityFlow(
            suffix: 'PASS',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            inspectionResult: 'passed',
            sampleSize: 100,
            passedSamples: 100,
            failedSamples: 0,
            lotStatus: 'released',
            producedQuantity: '1000.000',
            releasedQuantity: '980.000',
            rejectedQuantity: '20.000',
            nonconformitySeverity: 'minor',
            nonconformityStatus: 'corrected',
            category: 'Label alignment',
        );

        $this->createQualityFlow(
            suffix: 'FAIL',
            productId: $product->getKey(),
            lineId: $line->getKey(),
            inspectionResult: 'failed',
            sampleSize: 50,
            passedSamples: 38,
            failedSamples: 12,
            lotStatus: 'blocked',
            producedQuantity: '500.000',
            releasedQuantity: '0.000',
            rejectedQuantity: '100.000',
            nonconformitySeverity: 'critical',
            nonconformityStatus: 'open',
            category: 'Microbiology',
        );

        $summary = app(QualityKpiService::class)->summarize(
            $this->filter()
        );

        $this->assertSame(2, $summary->inspectionCount);
        $this->assertSame(1, $summary->passedInspectionCount);
        $this->assertSame(1, $summary->failedInspectionCount);
        $this->assertSame(50.0, $summary->inspectionPassPercentage);
        $this->assertSame(150, $summary->sampleSize);
        $this->assertSame(12, $summary->failedSampleQuantity);
        $this->assertSame(8.0, $summary->sampleFailurePercentage);
        $this->assertSame(2, $summary->lotCount);
        $this->assertSame(1, $summary->releasedLotCount);
        $this->assertSame(1, $summary->blockedLotCount);
        $this->assertSame(50.0, $summary->releasedLotPercentage);
        $this->assertSame(50.0, $summary->heldRejectedLotPercentage);
        $this->assertSame(2, $summary->nonconformityCount);
        $this->assertSame(1, $summary->openNonconformityCount);
        $this->assertSame(1, $summary->resolvedNonconformityCount);
        $this->assertSame(1, $summary->criticalNonconformityCount);
        $this->assertSame(100.0, $summary->nonconformitiesPer100Inspections);
        $this->assertCount(1, $summary->quantityUnits);
        $this->assertSame('1500.000', $summary->quantityUnits[0]->producedQuantity);
        $this->assertSame('980.000', $summary->quantityUnits[0]->releasedQuantity);
        $this->assertSame('120.000', $summary->quantityUnits[0]->rejectedQuantity);
        $this->assertSame(8.0, $summary->quantityUnits[0]->rejectedQuantityPercentage);
        $this->assertCount(1, $summary->byProductionLine);
        $this->assertCount(2, $summary->nonconformityCategories);
    }

    public function test_zero_denominators_return_null_rates(): void
    {
        $summary = app(QualityKpiService::class)->summarize(
            $this->filter()
        );

        $this->assertTrue($summary->isEmpty());
        $this->assertNull($summary->inspectionPassPercentage);
        $this->assertNull($summary->sampleFailurePercentage);
        $this->assertNull($summary->releasedLotPercentage);
        $this->assertNull($summary->nonconformitiesPer100Inspections);
    }

    private function filter(): QualityAnalyticsFilter
    {
        return new QualityAnalyticsFilter(
            startDate: CarbonImmutable::parse('2026-08-01', 'UTC'),
            endDate: CarbonImmutable::parse('2026-08-01', 'UTC'),
            timezone: 'UTC',
        );
    }

    private function createQualityFlow(
        string $suffix,
        int $productId,
        int $lineId,
        string $inspectionResult,
        int $sampleSize,
        int $passedSamples,
        int $failedSamples,
        string $lotStatus,
        string $producedQuantity,
        string $releasedQuantity,
        string $rejectedQuantity,
        string $nonconformitySeverity,
        string $nonconformityStatus,
        string $category,
    ): void {
        $now = CarbonImmutable::parse('2026-08-01 12:00:00', 'UTC');
        $batchExternalId = 'QUALITY-BATCH-'.$suffix;
        $inspectionExternalId = 'QUALITY-INSPECTION-'.$suffix;

        $orderId = DB::table('production_orders')->insertGetId([
            'order_number' => 'QUALITY-ORDER-'.$suffix,
            'product_id' => $productId,
            'production_line_id' => $lineId,
            'shift_id' => null,
            'planned_start_at' => '2026-08-01 06:00:00',
            'planned_end_at' => '2026-08-01 14:00:00',
            'target_quantity' => $producedQuantity,
            'quantity_unit' => 'bottles',
            'status' => 'completed',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('production_batches')->insert([
            'external_id' => $batchExternalId,
            'production_order_id' => $orderId,
            'batch_number' => 'QUALITY-BATCH-NUMBER-'.$suffix,
            'sequence_number' => 1,
            'status' => 'completed',
            'planned_quantity' => $producedQuantity,
            'actual_good_quantity' => $releasedQuantity,
            'actual_rejected_quantity' => $rejectedQuantity,
            'quantity_unit' => 'bottles',
            'scheduled_start_at' => '2026-08-01 06:00:00',
            'actual_start_at' => '2026-08-01 06:00:00',
            'actual_end_at' => '2026-08-01 14:00:00',
            'source_system' => 'manual',
            'import_status' => 'not_applicable',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('finished_lots')->insert([
            'external_id' => 'QUALITY-LOT-'.$suffix,
            'lot_number' => 'LOT-'.$suffix,
            'batch_external_id' => $batchExternalId,
            'product_external_id' => 'QUALITY-PRODUCT-'.$suffix,
            'status' => $lotStatus,
            'produced_at' => '2026-08-01 14:30:00',
            'produced_quantity' => $producedQuantity,
            'released_quantity' => $releasedQuantity,
            'rejected_quantity' => $rejectedQuantity,
            'quantity_unit' => 'bottles',
            'released_at' => $lotStatus === 'released'
                ? '2026-08-01 16:00:00'
                : null,
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('inspections')->insert([
            'external_id' => $inspectionExternalId,
            'inspection_number' => 'INS-'.$suffix,
            'batch_external_id' => $batchExternalId,
            'finished_lot_external_id' => 'QUALITY-LOT-'.$suffix,
            'inspection_type' => 'final_release',
            'result' => $inspectionResult,
            'inspected_at' => '2026-08-01 15:00:00',
            'sample_size' => $sampleSize,
            'passed_quantity' => $passedSamples,
            'failed_quantity' => $failedSamples,
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('nonconformities')->insert([
            'external_id' => 'QUALITY-NC-'.$suffix,
            'nonconformity_number' => 'NC-'.$suffix,
            'inspection_external_id' => $inspectionExternalId,
            'batch_external_id' => $batchExternalId,
            'severity' => $nonconformitySeverity,
            'status' => $nonconformityStatus,
            'category' => $category,
            'description' => 'Synthetic quality test nonconformity.',
            'detected_at' => '2026-08-01 15:10:00',
            'corrected_at' => in_array(
                $nonconformityStatus,
                ['corrected', 'closed'],
                true
            ) ? '2026-08-01 15:30:00' : null,
            'source_version' => 1,
            'source_updated_at' => $now,
            'import_status' => 'imported',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
