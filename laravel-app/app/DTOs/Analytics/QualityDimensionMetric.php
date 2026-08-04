<?php

namespace App\DTOs\Analytics;

use JsonSerializable;

final readonly class QualityDimensionMetric implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public int $inspectionCount,
        public int $passedInspectionCount,
        public int $failedInspectionCount,
        public int $conditionalInspectionCount,
        public int $pendingInspectionCount,
        public int $lotCount,
        public int $releasedLotCount,
        public int $blockedLotCount,
        public int $rejectedLotCount,
        public int $pendingLotCount,
        public int $nonconformityCount,
        public int $openNonconformityCount,
        public int $resolvedNonconformityCount,
        public ?float $inspectionPassPercentage,
        public ?float $releasedLotPercentage,
        public ?float $nonconformitiesPer100Inspections,
    ) {
    }

    /** @return array<string, float|int|string|null> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'inspection_count' => $this->inspectionCount,
            'passed_inspection_count' => $this->passedInspectionCount,
            'failed_inspection_count' => $this->failedInspectionCount,
            'conditional_inspection_count' =>
                $this->conditionalInspectionCount,
            'pending_inspection_count' => $this->pendingInspectionCount,
            'lot_count' => $this->lotCount,
            'released_lot_count' => $this->releasedLotCount,
            'blocked_lot_count' => $this->blockedLotCount,
            'rejected_lot_count' => $this->rejectedLotCount,
            'pending_lot_count' => $this->pendingLotCount,
            'nonconformity_count' => $this->nonconformityCount,
            'open_nonconformity_count' => $this->openNonconformityCount,
            'resolved_nonconformity_count' =>
                $this->resolvedNonconformityCount,
            'inspection_pass_percentage' =>
                $this->inspectionPassPercentage,
            'released_lot_percentage' => $this->releasedLotPercentage,
            'nonconformities_per_100_inspections' =>
                $this->nonconformitiesPer100Inspections,
        ];
    }

    /** @return array<string, float|int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
