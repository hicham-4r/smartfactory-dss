<?php

namespace App\DTOs\Dashboard;

use App\DTOs\Analytics\QualityKpiSummary;
use JsonSerializable;

final readonly class QualityDashboardSnapshot implements JsonSerializable
{
    public function __construct(
        public bool $hasData,
        public int $inspectionCount,
        public int $passedInspectionCount,
        public int $failedInspectionCount,
        public int $lotCount,
        public int $releasedLotCount,
        public int $nonconformityCount,
        public int $criticalNonconformityCount,
        public ?float $inspectionPassPercentage,
        public ?float $releasedLotPercentage,
    ) {
    }

    public static function fromSummary(
        QualityKpiSummary $summary
    ): self {
        return new self(
            hasData: ! $summary->isEmpty(),
            inspectionCount:
                $summary->inspectionCount,
            passedInspectionCount:
                $summary->passedInspectionCount,
            failedInspectionCount:
                $summary->failedInspectionCount,
            lotCount:
                $summary->lotCount,
            releasedLotCount:
                $summary->releasedLotCount,
            nonconformityCount:
                $summary->nonconformityCount,
            criticalNonconformityCount:
                $summary->criticalNonconformityCount,
            inspectionPassPercentage:
                $summary->inspectionPassPercentage,
            releasedLotPercentage:
                $summary->releasedLotPercentage,
        );
    }

    /**
     * @return array<string, bool|float|int|null>
     */
    public function toArray(): array
    {
        return [
            'has_data' => $this->hasData,
            'inspection_count' =>
                $this->inspectionCount,
            'passed_inspection_count' =>
                $this->passedInspectionCount,
            'failed_inspection_count' =>
                $this->failedInspectionCount,
            'lot_count' => $this->lotCount,
            'released_lot_count' =>
                $this->releasedLotCount,
            'nonconformity_count' =>
                $this->nonconformityCount,
            'critical_nonconformity_count' =>
                $this->criticalNonconformityCount,
            'inspection_pass_percentage' =>
                $this->inspectionPassPercentage,
            'released_lot_percentage' =>
                $this->releasedLotPercentage,
        ];
    }

    /**
     * @return array<string, bool|float|int|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
