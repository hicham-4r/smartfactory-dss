<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class ProductionSupervisorRecordItem implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $recordNumber,
        public string $productName,
        public string $productionLineName,
        public string $shiftName,
        public string $productionDate,
        public string $producedQuantity,
        public string $quantityUnit,
        public string $validationStatus,
        public ?string $recordedByName,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'record_number' => $this->recordNumber,
            'product_name' => $this->productName,
            'production_line_name' => $this->productionLineName,
            'shift_name' => $this->shiftName,
            'production_date' => $this->productionDate,
            'produced_quantity' => $this->producedQuantity,
            'quantity_unit' => $this->quantityUnit,
            'validation_status' => $this->validationStatus,
            'recorded_by_name' => $this->recordedByName,
        ];
    }

    /** @return array<string, int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
