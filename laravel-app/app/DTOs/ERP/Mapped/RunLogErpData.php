<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpRunLogType;
use Carbon\CarbonImmutable;

final readonly class RunLogErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $machineRunExternalId,
        public ?string $machineExternalId,
        public ErpRunLogType $logType,
        public string $message,
        public CarbonImmutable $recordedAt,
        public ?string $numericValue,
        public ?string $unit,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'machine_run_external_id' =>
                $this->machineRunExternalId,

            'machine_external_id' =>
                $this->machineExternalId,

            'log_type' =>
                $this->logType->value,

            'message' =>
                $this->message,

            'recorded_at' =>
                $this->recordedAt
                    ->utc()
                    ->toIso8601String(),

            'numeric_value' =>
                $this->numericValue,

            'unit' =>
                $this->unit,
        ]);
    }
}