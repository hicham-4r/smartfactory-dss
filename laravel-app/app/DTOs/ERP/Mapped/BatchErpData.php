<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\Production\ProductionBatchStatus;
use Carbon\CarbonImmutable;

final readonly class BatchErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $batchNumber,
        public string $workOrderExternalId,
        public int $sequenceNumber,
        public ProductionBatchStatus $status,
        public string $plannedQuantity,
        public string $actualGoodQuantity,
        public string $actualRejectedQuantity,
        public string $quantityUnit,
        public ?CarbonImmutable $scheduledStartAt,
        public ?CarbonImmutable $actualStartAt,
        public ?CarbonImmutable $actualEndAt,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'batch_number' =>
                $this->batchNumber,

            'work_order_external_id' =>
                $this->workOrderExternalId,

            'sequence_number' =>
                $this->sequenceNumber,

            'status' =>
                $this->status->value,

            'planned_quantity' =>
                $this->plannedQuantity,

            'actual_good_quantity' =>
                $this->actualGoodQuantity,

            'actual_rejected_quantity' =>
                $this->actualRejectedQuantity,

            'quantity_unit' =>
                $this->quantityUnit,

            'scheduled_start_at' =>
                $this->scheduledStartAt
                    ?->utc()
                    ->toIso8601String(),

            'actual_start_at' =>
                $this->actualStartAt
                    ?->utc()
                    ->toIso8601String(),

            'actual_end_at' =>
                $this->actualEndAt
                    ?->utc()
                    ->toIso8601String(),
        ]);
    }
}