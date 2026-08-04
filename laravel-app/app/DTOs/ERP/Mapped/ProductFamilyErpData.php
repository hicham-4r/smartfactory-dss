<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;

final readonly class ProductFamilyErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $code,
        public string $name,
        public ?string $description,
        public bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'code' => $this->code,
            'name' => $this->name,

            'description' =>
                $this->description,

            'is_active' =>
                $this->isActive,
        ]);
    }
}