<?php

namespace App\DTOs\Production;

use Carbon\CarbonImmutable;

final readonly class CreateProductionOrderData
{
    public function __construct(
        public int $productId,
        public int $productionLineId,
        public ?int $shiftId,
        public CarbonImmutable $plannedStartAt,
        public ?CarbonImmutable $plannedEndAt,
        public string $targetQuantity,
        public string $quantityUnit = 'bottles',
        public int $priority = 3,
        public ?string $instructions = null,
    ) {
    }
}