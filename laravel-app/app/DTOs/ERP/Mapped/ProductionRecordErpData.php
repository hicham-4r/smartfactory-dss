<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use Carbon\CarbonImmutable;

final readonly class ProductionRecordErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $recordNumber,
        public string $batchExternalId,
        public string $productionLineExternalId,
        public string $shiftExternalId,
        public ?string $operatorExternalId,
        public CarbonImmutable $productionDate,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
        public string $producedQuantity,
        public string $goodQuantity,
        public string $rejectedQuantity,
        public string $quantityUnit,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
        public ProductionRecordStatus $status,
        public ProductionValidationStatus $validationStatus,
        public ?CarbonImmutable $submittedAt,
        public ?CarbonImmutable $lockedAt,
        public ?string $notes,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'record_number' =>
                $this->recordNumber,

            'batch_external_id' =>
                $this->batchExternalId,

            'production_line_external_id' =>
                $this->productionLineExternalId,

            'shift_external_id' =>
                $this->shiftExternalId,

            'operator_external_id' =>
                $this->operatorExternalId,

            'production_date' =>
                $this->productionDate
                    ->format('Y-m-d'),

            'started_at' =>
                $this->startedAt
                    ?->utc()
                    ->toIso8601String(),

            'ended_at' =>
                $this->endedAt
                    ?->utc()
                    ->toIso8601String(),

            'produced_quantity' =>
                $this->producedQuantity,

            'good_quantity' =>
                $this->goodQuantity,

            'rejected_quantity' =>
                $this->rejectedQuantity,

            'quantity_unit' =>
                $this->quantityUnit,

            'runtime_minutes' =>
                $this->runtimeMinutes,

            'downtime_minutes' =>
                $this->downtimeMinutes,

            'status' =>
                $this->status->value,

            'validation_status' =>
                $this->validationStatus->value,

            'submitted_at' =>
                $this->submittedAt
                    ?->utc()
                    ->toIso8601String(),

            'locked_at' =>
                $this->lockedAt
                    ?->utc()
                    ->toIso8601String(),

            'notes' =>
                $this->notes,
        ]);
    }
}
