<?php

namespace App\DTOs\Analytics;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class QualityKpiSummary implements JsonSerializable
{
    /**
     * @param list<QualityUnitMetric> $quantityUnits
     * @param list<QualityDimensionMetric> $byProductionLine
     * @param list<QualityDimensionMetric> $byProduct
     * @param list<QualityDimensionMetric> $byProductFamily
     * @param list<QualityCategoryMetric> $nonconformityCategories
     */
    public function __construct(
        public QualityAnalyticsFilter $filter,
        public CarbonImmutable $generatedAt,
        public int $inspectionCount,
        public int $passedInspectionCount,
        public int $failedInspectionCount,
        public int $conditionalInspectionCount,
        public int $pendingInspectionCount,
        public int $sampleSize,
        public int $passedSampleQuantity,
        public int $failedSampleQuantity,
        public int $lotCount,
        public int $releasedLotCount,
        public int $blockedLotCount,
        public int $rejectedLotCount,
        public int $pendingLotCount,
        public int $nonconformityCount,
        public int $openNonconformityCount,
        public int $resolvedNonconformityCount,
        public int $minorNonconformityCount,
        public int $majorNonconformityCount,
        public int $criticalNonconformityCount,
        public ?float $inspectionPassPercentage,
        public ?float $sampleFailurePercentage,
        public ?float $releasedLotPercentage,
        public ?float $heldRejectedLotPercentage,
        public ?float $nonconformitiesPer100Inspections,
        public array $quantityUnits,
        public array $byProductionLine,
        public array $byProduct,
        public array $byProductFamily,
        public array $nonconformityCategories,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->inspectionCount === 0
            && $this->lotCount === 0
            && $this->nonconformityCount === 0;
    }

    public function dataBasisLabel(): string
    {
        return 'Inspection KPIs use inspected_at; '
            .'finished-lot KPIs use produced_at; '
            .'nonconformities use detected_at. '
            .'Quantities are never combined across units. '
            .'All displayed records are simulated ERP or DSS prototype data.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'generated_at' => $this->generatedAt
                ->utc()
                ->toIso8601String(),
            'data_basis' => $this->dataBasisLabel(),
            'is_empty' => $this->isEmpty(),
            'inspection_count' => $this->inspectionCount,
            'passed_inspection_count' => $this->passedInspectionCount,
            'failed_inspection_count' => $this->failedInspectionCount,
            'conditional_inspection_count' =>
                $this->conditionalInspectionCount,
            'pending_inspection_count' => $this->pendingInspectionCount,
            'sample_size' => $this->sampleSize,
            'passed_sample_quantity' => $this->passedSampleQuantity,
            'failed_sample_quantity' => $this->failedSampleQuantity,
            'lot_count' => $this->lotCount,
            'released_lot_count' => $this->releasedLotCount,
            'blocked_lot_count' => $this->blockedLotCount,
            'rejected_lot_count' => $this->rejectedLotCount,
            'pending_lot_count' => $this->pendingLotCount,
            'nonconformity_count' => $this->nonconformityCount,
            'open_nonconformity_count' => $this->openNonconformityCount,
            'resolved_nonconformity_count' =>
                $this->resolvedNonconformityCount,
            'minor_nonconformity_count' =>
                $this->minorNonconformityCount,
            'major_nonconformity_count' =>
                $this->majorNonconformityCount,
            'critical_nonconformity_count' =>
                $this->criticalNonconformityCount,
            'inspection_pass_percentage' =>
                $this->inspectionPassPercentage,
            'sample_failure_percentage' => $this->sampleFailurePercentage,
            'released_lot_percentage' => $this->releasedLotPercentage,
            'held_rejected_lot_percentage' =>
                $this->heldRejectedLotPercentage,
            'nonconformities_per_100_inspections' =>
                $this->nonconformitiesPer100Inspections,
            'quantity_units' => array_map(
                static fn (QualityUnitMetric $metric): array =>
                    $metric->toArray(),
                $this->quantityUnits
            ),
            'by_production_line' => array_map(
                static fn (QualityDimensionMetric $metric): array =>
                    $metric->toArray(),
                $this->byProductionLine
            ),
            'by_product' => array_map(
                static fn (QualityDimensionMetric $metric): array =>
                    $metric->toArray(),
                $this->byProduct
            ),
            'by_product_family' => array_map(
                static fn (QualityDimensionMetric $metric): array =>
                    $metric->toArray(),
                $this->byProductFamily
            ),
            'nonconformity_categories' => array_map(
                static fn (QualityCategoryMetric $metric): array =>
                    $metric->toArray(),
                $this->nonconformityCategories
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
