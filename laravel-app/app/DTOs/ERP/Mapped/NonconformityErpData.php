<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpNonconformitySeverity;
use App\Enums\ERP\ErpNonconformityStatus;
use Carbon\CarbonImmutable;

final readonly class NonconformityErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $nonconformityNumber,
        public string $inspectionExternalId,
        public ?string $batchExternalId,
        public ErpNonconformitySeverity $severity,
        public ErpNonconformityStatus $status,
        public string $category,
        public string $description,
        public CarbonImmutable $detectedAt,
        public ?CarbonImmutable $correctedAt,
        public ?string $correctiveAction,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'nonconformity_number' =>
                $this->nonconformityNumber,

            'inspection_external_id' =>
                $this->inspectionExternalId,

            'batch_external_id' =>
                $this->batchExternalId,

            'severity' =>
                $this->severity->value,

            'status' =>
                $this->status->value,

            'category' =>
                $this->category,

            'description' =>
                $this->description,

            'detected_at' =>
                $this->detectedAt
                    ->utc()
                    ->toIso8601String(),

            'corrected_at' =>
                $this->correctedAt
                    ?->utc()
                    ->toIso8601String(),

            'corrective_action' =>
                $this->correctiveAction,
        ]);
    }
}