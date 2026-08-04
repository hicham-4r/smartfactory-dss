<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;

final readonly class MachineErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $code,
        public string $name,
        public string $productionLineExternalId,
        public string $machineType,
        public ?string $manufacturer,
        public ?string $model,
        public ?string $serialNumber,
        public ?int $sequenceNumber,
        public bool $isCritical,
        public bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'code' => $this->code,
            'name' => $this->name,

            'production_line_external_id' =>
                $this->productionLineExternalId,

            'machine_type' =>
                $this->machineType,

            'manufacturer' =>
                $this->manufacturer,

            'model' => $this->model,

            'serial_number' =>
                $this->serialNumber,

            'sequence_number' =>
                $this->sequenceNumber,

            'is_critical' =>
                $this->isCritical,

            'is_active' =>
                $this->isActive,
        ]);
    }
}
