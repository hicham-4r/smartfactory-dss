<?php

namespace App\DTOs\Production;

use Carbon\CarbonImmutable;

final readonly class CreateProductionRecordData
{
    public function __construct(
        public int $productionBatchId,
        public int $shiftId,
        public int $operatorId,
        public CarbonImmutable $productionDate,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
        public string $producedQuantity,
        public string $goodQuantity,
        public string $rejectedQuantity,
        public string $quantityUnit = 'bottles',
        public int $runtimeMinutes = 0,
        public int $downtimeMinutes = 0,
        public ?string $notes = null,
    ) {
    }
}