<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use Carbon\CarbonImmutable;

final readonly class OperatorAssignmentErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $operatorExternalId,
        public string $productionLineExternalId,
        public string $shiftExternalId,
        public CarbonImmutable $validFrom,
        public ?CarbonImmutable $validUntil,
        public bool $isPrimary,
        public bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'operator_external_id' =>
                $this->operatorExternalId,

            'production_line_external_id' =>
                $this->productionLineExternalId,

            'shift_external_id' =>
                $this->shiftExternalId,

            'valid_from' =>
                $this->validFrom
                    ->toDateString(),

            'valid_until' =>
                $this->validUntil
                    ?->toDateString(),

            'is_primary' =>
                $this->isPrimary,

            'is_active' =>
                $this->isActive,
        ]);
    }
}
