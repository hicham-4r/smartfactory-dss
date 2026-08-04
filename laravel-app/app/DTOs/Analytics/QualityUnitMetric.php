<?php

namespace App\DTOs\Analytics;

use JsonSerializable;

final readonly class QualityUnitMetric implements JsonSerializable
{
    public function __construct(
        public string $quantityUnit,
        public int $lotCount,
        public string $producedQuantity,
        public string $releasedQuantity,
        public string $rejectedQuantity,
        public ?float $releasedQuantityPercentage,
        public ?float $rejectedQuantityPercentage,
    ) {
    }

    /** @return array<string, float|int|string|null> */
    public function toArray(): array
    {
        return [
            'quantity_unit' => $this->quantityUnit,
            'lot_count' => $this->lotCount,
            'produced_quantity' => $this->producedQuantity,
            'released_quantity' => $this->releasedQuantity,
            'rejected_quantity' => $this->rejectedQuantity,
            'released_quantity_percentage' =>
                $this->releasedQuantityPercentage,
            'rejected_quantity_percentage' =>
                $this->rejectedQuantityPercentage,
        ];
    }

    /** @return array<string, float|int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
