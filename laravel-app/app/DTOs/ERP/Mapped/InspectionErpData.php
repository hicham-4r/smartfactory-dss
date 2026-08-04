<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpInspectionResult;
use Carbon\CarbonImmutable;

final readonly class InspectionErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $inspectionNumber,
        public string $batchExternalId,
        public ?string $finishedLotExternalId,
        public ?string $inspectorExternalId,
        public string $inspectionType,
        public ErpInspectionResult $result,
        public CarbonImmutable $inspectedAt,
        public ?int $sampleSize,
        public ?int $passedQuantity,
        public ?int $failedQuantity,
        public ?string $notes,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'inspection_number' =>
                $this->inspectionNumber,

            'batch_external_id' =>
                $this->batchExternalId,

            'finished_lot_external_id' =>
                $this->finishedLotExternalId,

            'inspector_external_id' =>
                $this->inspectorExternalId,

            'inspection_type' =>
                $this->inspectionType,

            'result' =>
                $this->result->value,

            'inspected_at' =>
                $this->inspectedAt
                    ->utc()
                    ->toIso8601String(),

            'sample_size' =>
                $this->sampleSize,

            'passed_quantity' =>
                $this->passedQuantity,

            'failed_quantity' =>
                $this->failedQuantity,

            'notes' =>
                $this->notes,
        ]);
    }
}