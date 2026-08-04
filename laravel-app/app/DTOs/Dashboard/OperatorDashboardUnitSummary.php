<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class OperatorDashboardUnitSummary implements JsonSerializable
{
    public function __construct(
        public string $quantityUnit,
        public int $recordCount,
        public string $producedQuantity,
        public string $goodQuantity,
        public string $rejectedQuantity,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'quantity_unit' => $this->quantityUnit,
            'record_count' => $this->recordCount,
            'produced_quantity' =>
                $this->producedQuantity,
            'good_quantity' => $this->goodQuantity,
            'rejected_quantity' =>
                $this->rejectedQuantity,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
