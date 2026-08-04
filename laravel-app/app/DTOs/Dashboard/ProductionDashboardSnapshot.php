<?php

namespace App\DTOs\Dashboard;

use App\DTOs\Analytics\ProductionKpiSummary;
use JsonSerializable;

final readonly class ProductionDashboardSnapshot implements JsonSerializable
{
    public function __construct(
        public bool $hasData,
        public bool $isProvisional,
        public int $recordCount,
        public int $validatedRecordCount,
        public int $provisionalRecordCount,
        public int $targetOrderCount,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
    ) {
    }

    public static function fromSummary(
        ProductionKpiSummary $summary
    ): self {
        return new self(
            hasData: ! $summary->isEmpty(),
            isProvisional: $summary->isProvisional(),
            recordCount: $summary->recordCount,
            validatedRecordCount:
                $summary->validatedRecordCount,
            provisionalRecordCount:
                $summary->provisionalRecordCount,
            targetOrderCount:
                $summary->targetOrderCount,
            runtimeMinutes:
                $summary->runtimeMinutes,
            downtimeMinutes:
                $summary->downtimeMinutes,
        );
    }

    /**
     * @return array<string, bool|int>
     */
    public function toArray(): array
    {
        return [
            'has_data' => $this->hasData,
            'is_provisional' => $this->isProvisional,
            'record_count' => $this->recordCount,
            'validated_record_count' =>
                $this->validatedRecordCount,
            'provisional_record_count' =>
                $this->provisionalRecordCount,
            'target_order_count' =>
                $this->targetOrderCount,
            'runtime_minutes' =>
                $this->runtimeMinutes,
            'downtime_minutes' =>
                $this->downtimeMinutes,
        ];
    }

    /**
     * @return array<string, bool|int>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
