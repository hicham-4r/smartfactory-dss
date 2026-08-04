<?php

namespace App\DTOs\Analytics;

use JsonSerializable;

final readonly class ProductionMetricRow implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $quantityUnit,
        public int $targetCount,
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
        public ?float $qualityYieldPercentage,
        public ?float $goodOutputEfficiencyPercentage,
        public ?string $averageProductionRatePerHour,
        public ?float $observedUtilizationPercentage,
    ) {
    }

    public function isProvisional(): bool
    {
        return $this->provisionalRecordCount > 0;
    }

    public function hasActualProduction(): bool
    {
        return $this->recordCount > 0;
    }

    public function hasTarget(): bool
    {
        return (float) $this->targetQuantity > 0;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'quantity_unit' => $this->quantityUnit,
            'target_count' => $this->targetCount,
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
            'quality_yield_percentage' => $this->qualityYieldPercentage,
            'good_output_efficiency_percentage' =>
                $this->goodOutputEfficiencyPercentage,
            'average_production_rate_per_hour' =>
                $this->averageProductionRatePerHour,
            'observed_utilization_percentage' =>
                $this->observedUtilizationPercentage,
        ];
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
