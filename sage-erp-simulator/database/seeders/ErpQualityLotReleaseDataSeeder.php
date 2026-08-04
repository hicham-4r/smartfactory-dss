<?php

namespace Database\Seeders;

use App\Models\ErpFinishedLotRelease;
use App\Models\ErpProductionBatch;
use App\Models\ErpQualityInspection;
use App\Models\ErpQualityTestResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ErpQualityLotReleaseDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            mt_srand(20260726);

            $this->deleteExistingQualityData();

            $batches = ErpProductionBatch::query()
                ->with([
                    'productionOrder.product.packagingFormat',
                    'productionOrder.productionLine',
                    'shift',
                ])
                ->orderBy('id')
                ->get();

            if ($batches->isEmpty()) {
                throw new RuntimeException(
                    'Production batches must be seeded first.'
                );
            }

            foreach ($batches as $batch) {
                $this->createInspectionAndRelease($batch);
            }
        });
    }

    private function deleteExistingQualityData(): void
    {
        ErpFinishedLotRelease::query()->delete();
        ErpQualityTestResult::query()->delete();
        ErpQualityInspection::query()->delete();
    }

    private function createInspectionAndRelease(
        ErpProductionBatch $batch
    ): void {
        $order = $batch->productionOrder;
        $product = $order->product;
        $line = $order->productionLine;
        $shift = $batch->shift;

        $batchEnd = CarbonImmutable::instance(
            $batch->actual_end_at
                ?? $batch->scheduled_end_at
        );

        $sampledAt = $batchEnd->addMinutes(
            mt_rand(10, 30)
        );

        $inspectionStartedAt = $sampledAt->addMinutes(
            mt_rand(5, 15)
        );

        $inspectionCompletedAt =
            $inspectionStartedAt->addMinutes(
                mt_rand(45, 120)
            );

        $shouldPass = $batch->quality_status === 'approved';

        $forcedFailedTestCode = $shouldPass
            ? null
            : $this->randomFailedTestCode();

        $testDefinitions = $this->buildTestDefinitions(
            product: $product,
            forcedFailedTestCode: $forcedFailedTestCode,
        );

        $passedTests = collect($testDefinitions)
            ->where('result', 'passed')
            ->count();

        $overallScore = round(
            ($passedTests / count($testDefinitions)) * 100,
            2
        );

        $inspectionResult = $passedTests
            === count($testDefinitions)
                ? 'passed'
                : 'failed';

        $isLateArrival = mt_rand(1, 100) <= 4;

        $sourceUpdatedAt = $isLateArrival
            ? $inspectionCompletedAt->addDays(
                mt_rand(1, 3)
            )
            : $inspectionCompletedAt->addMinutes(
                mt_rand(5, 30)
            );

        $inspectionNumber = sprintf(
            'QI-%s-%s',
            $batch->scheduled_start_at->format('Ymd'),
            $batch->batch_number
        );

        $inspection = ErpQualityInspection::query()->create([
            'production_batch_id' => $batch->id,
            'product_id' => $product->id,
            'production_line_id' => $line->id,
            'shift_id' => $shift->id,
            'inspection_number' => $inspectionNumber,
            'inspection_type' => 'final_release',
            'sampled_at' => $sampledAt,
            'inspection_started_at' =>
                $inspectionStartedAt,
            'inspection_completed_at' =>
                $inspectionCompletedAt,
            'inspector_name' => sprintf(
                'Simulated Quality Inspector %d',
                mt_rand(1, 5)
            ),
            'status' => 'completed',
            'result' => $inspectionResult,
            'overall_score_percent' => $overallScore,
            'nonconformity_code' =>
                $inspectionResult === 'failed'
                    ? $this->nonconformityCode(
                        $forcedFailedTestCode
                    )
                    : null,
            'nonconformity_description' =>
                $inspectionResult === 'failed'
                    ? 'Synthetic quality result outside the approved specification.'
                    : null,
            'corrective_action' =>
                $inspectionResult === 'failed'
                    ? 'Block the lot, investigate the deviation, and prevent release.'
                    : null,
            'is_late_arrival' => $isLateArrival,
            'source_updated_at' => $sourceUpdatedAt,
        ]);

        foreach ($testDefinitions as $testIndex => $test) {
            ErpQualityTestResult::query()->create([
                'quality_inspection_id' =>
                    $inspection->id,
                'test_code' => $test['test_code'],
                'test_name' => $test['test_name'],
                'test_category' =>
                    $test['test_category'],
                'numeric_value' =>
                    $test['numeric_value'],
                'text_value' => $test['text_value'],
                'unit' => $test['unit'],
                'minimum_specification' =>
                    $test['minimum_specification'],
                'maximum_specification' =>
                    $test['maximum_specification'],
                'result' => $test['result'],
                'tested_at' =>
                    $inspectionStartedAt->addMinutes(
                        ($testIndex + 1) * 10
                    ),
                'notes' =>
                    'Synthetic laboratory or packaging quality test.',
            ]);
        }

        $this->createLotRelease(
            batch: $batch,
            inspection: $inspection,
            inspectionCompletedAt:
                $inspectionCompletedAt,
        );
    }

    private function buildTestDefinitions(
        $product,
        ?string $forcedFailedTestCode
    ): array {
        $volumeMl = $product
            ->packagingFormat
            ->volume_ml;

        $brixRange = $this->brixRangeForProduct(
            $product->beverage_type
        );

        $tests = [
            $this->numericTest(
                code: 'BRIX',
                name: 'Soluble solids measurement',
                category: 'physicochemical',
                minimum: $brixRange[0],
                maximum: $brixRange[1],
                unit: '°Bx',
                forceFailure:
                    $forcedFailedTestCode === 'BRIX',
            ),

            $this->numericTest(
                code: 'PH',
                name: 'Acidity measurement',
                category: 'physicochemical',
                minimum: 3.00,
                maximum: 4.20,
                unit: 'pH',
                forceFailure:
                    $forcedFailedTestCode === 'PH',
            ),

            $this->numericTest(
                code: 'FILL_VOLUME',
                name: 'Package fill-volume control',
                category: 'packaging',
                minimum: $volumeMl * 0.98,
                maximum: $volumeMl * 1.02,
                unit: 'mL',
                forceFailure:
                    $forcedFailedTestCode ===
                    'FILL_VOLUME',
            ),

            $this->textTest(
                code: 'PACKAGE_INTEGRITY',
                name: 'Package integrity inspection',
                category: 'packaging',
                passedValue: 'compliant',
                failedValue: 'seal defect detected',
                forceFailure:
                    $forcedFailedTestCode ===
                    'PACKAGE_INTEGRITY',
            ),

            $this->textTest(
                code: 'MICROBIOLOGY',
                name: 'Microbiological compliance',
                category: 'microbiology',
                passedValue: 'compliant',
                failedValue: 'non-compliant result',
                forceFailure:
                    $forcedFailedTestCode ===
                    'MICROBIOLOGY',
            ),

            $this->numericTest(
                code: 'SENSORY_SCORE',
                name: 'Sensory evaluation score',
                category: 'sensory',
                minimum: 7.00,
                maximum: 10.00,
                unit: '/10',
                forceFailure:
                    $forcedFailedTestCode ===
                    'SENSORY_SCORE',
            ),
        ];

        return $tests;
    }

    private function numericTest(
        string $code,
        string $name,
        string $category,
        float $minimum,
        float $maximum,
        string $unit,
        bool $forceFailure
    ): array {
        if ($forceFailure) {
            $value = mt_rand(1, 2) === 1
                ? $minimum - mt_rand(10, 80) / 100
                : $maximum + mt_rand(10, 80) / 100;

            $result = 'failed';
        } else {
            $value = $minimum
                + (
                    mt_rand(0, 10000) / 10000
                    * ($maximum - $minimum)
                );

            $result = 'passed';
        }

        return [
            'test_code' => $code,
            'test_name' => $name,
            'test_category' => $category,
            'numeric_value' => round($value, 4),
            'text_value' => null,
            'unit' => $unit,
            'minimum_specification' => round(
                $minimum,
                4
            ),
            'maximum_specification' => round(
                $maximum,
                4
            ),
            'result' => $result,
        ];
    }

    private function textTest(
        string $code,
        string $name,
        string $category,
        string $passedValue,
        string $failedValue,
        bool $forceFailure
    ): array {
        return [
            'test_code' => $code,
            'test_name' => $name,
            'test_category' => $category,
            'numeric_value' => null,
            'text_value' => $forceFailure
                ? $failedValue
                : $passedValue,
            'unit' => null,
            'minimum_specification' => null,
            'maximum_specification' => null,
            'result' => $forceFailure
                ? 'failed'
                : 'passed',
        ];
    }

    private function createLotRelease(
        ErpProductionBatch $batch,
        ErpQualityInspection $inspection,
        CarbonImmutable $inspectionCompletedAt
    ): void {
        if ($inspection->result === 'failed') {
            $decision = 'rejected';
        } elseif (mt_rand(1, 100) <= 4) {
            $decision = 'blocked';
        } else {
            $decision = 'released';
        }

        $decisionAt = $inspectionCompletedAt
            ->addMinutes(mt_rand(15, 180));

        $isLateArrival = mt_rand(1, 100) <= 4;

        $sourceUpdatedAt = $isLateArrival
            ? $decisionAt->addDays(mt_rand(1, 3))
            : $decisionAt->addMinutes(mt_rand(5, 30));

        [$approvedQuantity, $blockedQuantity, $rejectedQuantity]
            = $this->releaseQuantities(
                batch: $batch,
                decision: $decision
            );

        $releaseNumber = sprintf(
            'REL-%s-%s',
            $batch->scheduled_start_at->format('Ymd'),
            $batch->batch_number
        );

        ErpFinishedLotRelease::query()->create([
            'production_batch_id' => $batch->id,
            'quality_inspection_id' => $inspection->id,
            'release_number' => $releaseNumber,
            'lot_number' => $batch->lot_number,
            'decision' => $decision,
            'warehouse_status' => match ($decision) {
                'released' => 'available',
                'blocked' => 'quarantine',
                'rejected' => 'rejected',
            },
            'decision_at' => $decisionAt,
            'released_at' => $decision === 'released'
                ? $decisionAt
                : null,
            'released_by' => $decision === 'released'
                ? sprintf(
                    'Simulated Quality Manager %d',
                    mt_rand(1, 3)
                )
                : null,
            'quality_certificate_number' =>
                $decision === 'released'
                    ? sprintf(
                        'CERT-%s',
                        $batch->lot_number
                    )
                    : null,
            'approved_quantity' => $approvedQuantity,
            'blocked_quantity' => $blockedQuantity,
            'rejected_quantity' =>
                $rejectedQuantity,
            'expiry_date' => $batch->expiry_date,
            'decision_reason' =>
                $this->decisionReason($decision),
            'is_late_arrival' => $isLateArrival,
            'source_updated_at' => $sourceUpdatedAt,
        ]);
    }

    private function releaseQuantities(
        ErpProductionBatch $batch,
        string $decision
    ): array {
        return match ($decision) {
            'released' => [
                $batch->good_quantity,
                0,
                $batch->rejected_quantity,
            ],

            'blocked' => [
                0,
                $batch->good_quantity,
                $batch->rejected_quantity,
            ],

            'rejected' => [
                0,
                0,
                $batch->gross_quantity,
            ],
        };
    }

    private function decisionReason(
        string $decision
    ): string {
        return match ($decision) {
            'released' =>
                'All simulated release requirements were satisfied.',

            'blocked' =>
                'The lot passed quality tests but remains under a simulated administrative hold.',

            'rejected' =>
                'The lot failed one or more simulated quality requirements.',
        };
    }

    private function brixRangeForProduct(
        string $beverageType
    ): array {
        return match ($beverageType) {
            'pure_juice' => [10.50, 14.50],
            'nectar' => [9.00, 13.00],
            'juice_milk_blend' => [8.00, 12.00],
            'iced_tea' => [5.00, 9.00],
            default => [7.00, 12.00],
        };
    }

    private function randomFailedTestCode(): string
    {
        $codes = [
            'BRIX',
            'PH',
            'FILL_VOLUME',
            'PACKAGE_INTEGRITY',
            'MICROBIOLOGY',
            'SENSORY_SCORE',
        ];

        return $codes[array_rand($codes)];
    }

    private function nonconformityCode(
        ?string $testCode
    ): string {
        return match ($testCode) {
            'BRIX' => 'NC-BRIX',
            'PH' => 'NC-PH',
            'FILL_VOLUME' => 'NC-FILL',
            'PACKAGE_INTEGRITY' => 'NC-PACK',
            'MICROBIOLOGY' => 'NC-MICRO',
            'SENSORY_SCORE' => 'NC-SENSORY',
            default => 'NC-GENERAL',
        };
    }
}