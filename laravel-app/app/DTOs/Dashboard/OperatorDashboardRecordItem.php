<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class OperatorDashboardRecordItem implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $recordNumber,
        public string $productName,
        public string $productionLineName,
        public string $shiftName,
        public string $productionDate,
        public string $producedQuantity,
        public string $goodQuantity,
        public string $rejectedQuantity,
        public string $quantityUnit,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
        public string $status,
        public string $validationStatus,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'record_number' => $this->recordNumber,
            'product_name' => $this->productName,
            'production_line_name' =>
                $this->productionLineName,
            'shift_name' => $this->shiftName,
            'production_date' => $this->productionDate,
            'produced_quantity' =>
                $this->producedQuantity,
            'good_quantity' => $this->goodQuantity,
            'rejected_quantity' =>
                $this->rejectedQuantity,
            'quantity_unit' => $this->quantityUnit,
            'runtime_minutes' => $this->runtimeMinutes,
            'downtime_minutes' =>
                $this->downtimeMinutes,
            'status' => $this->status,
            'validation_status' =>
                $this->validationStatus,
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
