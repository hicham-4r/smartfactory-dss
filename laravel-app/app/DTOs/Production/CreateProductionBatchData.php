<?php

namespace App\DTOs\Production;

use Carbon\CarbonImmutable;

final readonly class CreateProductionBatchData
{
    public function __construct(
        public int $productionOrderId,
        public string $plannedQuantity,
        public ?CarbonImmutable $scheduledStartAt,
        public string $quantityUnit = 'bottles',
    ) {
    }
}