<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;

final readonly class ShiftErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $code,
        public string $name,
        public string $startTime,
        public string $endTime,
        public bool $crossesMidnight,
        public bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'code' => $this->code,
            'name' => $this->name,

            'start_time' =>
                $this->startTime,

            'end_time' =>
                $this->endTime,

            'crosses_midnight' =>
                $this->crossesMidnight,

            'is_active' =>
                $this->isActive,
        ]);
    }
}