<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class OperatorDashboardOrderItem implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public string $productName,
        public string $productionLineName,
        public ?string $shiftName,
        public string $status,
        public string $plannedStartAt,
        public string $targetQuantity,
        public string $quantityUnit,
        public int $priority,
        public ?int $actionBatchId,
        public ?string $actionBatchNumber,
    ) {
    }

    public function hasActionableBatch(): bool
    {
        return $this->actionBatchId !== null;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber,
            'product_name' => $this->productName,
            'production_line_name' =>
                $this->productionLineName,
            'shift_name' => $this->shiftName,
            'status' => $this->status,
            'planned_start_at' => $this->plannedStartAt,
            'target_quantity' => $this->targetQuantity,
            'quantity_unit' => $this->quantityUnit,
            'priority' => $this->priority,
            'action_batch_id' => $this->actionBatchId,
            'action_batch_number' =>
                $this->actionBatchNumber,
            'has_actionable_batch' =>
                $this->hasActionableBatch(),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
