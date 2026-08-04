<?php

namespace App\DTOs\Production;

use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use Carbon\CarbonImmutable;

final readonly class CreateProductionEventData
{
    public function __construct(
        public int $productionBatchId,
        public ?int $productionRecordId,
        public ?int $machineId,
        public ?int $shiftId,
        public ?int $operatorId,
        public ProductionEventType $eventType,
        public ProductionEventSeverity $severity,
        public string $title,
        public ?string $description,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
    ) {
    }
}