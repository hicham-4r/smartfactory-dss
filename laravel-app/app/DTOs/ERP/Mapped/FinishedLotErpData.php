<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpFinishedLotStatus;
use Carbon\CarbonImmutable;

final readonly class FinishedLotErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $lotNumber,
        public string $batchExternalId,
        public string $productExternalId,
        public ErpFinishedLotStatus $status,
        public CarbonImmutable $producedAt,
        public ?CarbonImmutable $expiryDate,
        public string $producedQuantity,
        public string $releasedQuantity,
        public string $rejectedQuantity,
        public string $quantityUnit,
        public ?CarbonImmutable $releasedAt,
        public ?string $releasedByExternalId,
        public ?string $releaseNotes,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'lot_number' =>
                $this->lotNumber,

            'batch_external_id' =>
                $this->batchExternalId,

            'product_external_id' =>
                $this->productExternalId,

            'status' =>
                $this->status->value,

            'produced_at' =>
                $this->producedAt
                    ->utc()
                    ->toIso8601String(),

            'expiry_date' =>
                $this->expiryDate
                    ?->format('Y-m-d'),

            'produced_quantity' =>
                $this->producedQuantity,

            'released_quantity' =>
                $this->releasedQuantity,

            'rejected_quantity' =>
                $this->rejectedQuantity,

            'quantity_unit' =>
                $this->quantityUnit,

            'released_at' =>
                $this->releasedAt
                    ?->utc()
                    ->toIso8601String(),

            'released_by_external_id' =>
                $this->releasedByExternalId,

            'release_notes' =>
                $this->releaseNotes,
        ]);
    }
}