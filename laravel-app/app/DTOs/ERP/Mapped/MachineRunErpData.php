<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpMachineRunStatus;
use Carbon\CarbonImmutable;

final readonly class MachineRunErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $runNumber,
        public string $batchExternalId,
        public string $machineExternalId,
        public ?string $shiftExternalId,
        public ?string $operatorExternalId,
        public ErpMachineRunStatus $status,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
        public string $producedQuantity,
        public string $goodQuantity,
        public string $rejectedQuantity,
        public string $quantityUnit,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'run_number' =>
                $this->runNumber,

            'batch_external_id' =>
                $this->batchExternalId,

            'machine_external_id' =>
                $this->machineExternalId,

            'shift_external_id' =>
                $this->shiftExternalId,

            'operator_external_id' =>
                $this->operatorExternalId,

            'status' =>
                $this->status->value,

            'started_at' =>
                $this->startedAt
                    ->utc()
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
        ]);
    }
}