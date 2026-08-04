<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;

final readonly class ProductErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $code,
        public string $name,
        public string $productFamilyExternalId,
        public ?string $sku,
        public string $quantityUnit,
        public bool $isActive,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'code' => $this->code,
            'name' => $this->name,

            'product_family_external_id' =>
                $this->productFamilyExternalId,

            'sku' => $this->sku,

            'quantity_unit' =>
                $this->quantityUnit,

            'is_active' =>
                $this->isActive,
        ]);
    }
}