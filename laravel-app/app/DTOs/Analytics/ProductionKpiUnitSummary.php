<?php

namespace App\DTOs\Analytics;

final readonly class ProductionKpiUnitSummary
{
    public function __construct(
        public string $quantityUnit,
        public int $targetOrderCount,
        public int $recordCount,
        public int $validatedRecordCount,
        public int $provisionalRecordCount,
        public string $targetQuantity,
        public string $actualQuantity,
        public string $goodQuantity,
        public string $rejectedQuantity,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
        public ?float $achievementPercentage,
        public ?float $rejectionPercentage,
        public ?string $averageProductionRatePerHour,
        public ?float $observedUtilizationPercentage,
    ) {
    }

    public function hasTarget(): bool
    {
        return (float) $this->targetQuantity > 0;
    }

    public function hasActualProduction(): bool
    {
        return $this->recordCount > 0;
    }

    public function isProvisional(): bool
    {
        return $this->provisionalRecordCount > 0;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'quantity_unit' => $this->quantityUnit,
            'target_order_count' => $this->targetOrderCount,
            'record_count' => $this->recordCount,
            'validated_record_count' => $this->validatedRecordCount,
            'provisional_record_count' => $this->provisionalRecordCount,
            'is_provisional' => $this->isProvisional(),
            'target_quantity' => $this->targetQuantity,
            'actual_quantity' => $this->actualQuantity,
            'good_quantity' => $this->goodQuantity,
            'rejected_quantity' => $this->rejectedQuantity,
            'runtime_minutes' => $this->runtimeMinutes,
            'downtime_minutes' => $this->downtimeMinutes,
            'achievement_percentage' => $this->achievementPercentage,
            'rejection_percentage' => $this->rejectionPercentage,
            'average_production_rate_per_hour' =>
                $this->averageProductionRatePerHour,
            'observed_utilization_percentage' =>
                $this->observedUtilizationPercentage,
        ];
    }
}
