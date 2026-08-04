<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\Production\ProductionOrderStatus;
use Carbon\CarbonImmutable;

final readonly class WorkOrderErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $orderNumber,
        public string $productExternalId,
        public string $productionLineExternalId,
        public ?string $shiftExternalId,
        public ProductionOrderStatus $status,
        public CarbonImmutable $plannedStartAt,
        public ?CarbonImmutable $plannedEndAt,
        public string $targetQuantity,
        public string $quantityUnit,
        public int $priority,
        public ?string $instructions,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'order_number' =>
                $this->orderNumber,

            'product_external_id' =>
                $this->productExternalId,

            'production_line_external_id' =>
                $this->productionLineExternalId,

            'shift_external_id' =>
                $this->shiftExternalId,

            'status' =>
                $this->status->value,

            'planned_start_at' =>
                $this->plannedStartAt
                    ->utc()
                    ->toIso8601String(),

            'planned_end_at' =>
                $this->plannedEndAt
                    ?->utc()
                    ->toIso8601String(),

            'target_quantity' =>
                $this->targetQuantity,

            'quantity_unit' =>
                $this->quantityUnit,

            'priority' =>
                $this->priority,

            'instructions' =>
                $this->instructions,
        ]);
    }
}