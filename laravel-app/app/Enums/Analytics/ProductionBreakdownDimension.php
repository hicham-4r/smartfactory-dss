<?php

namespace App\Enums\Analytics;

enum ProductionBreakdownDimension: string
{
    case ProductionLine = 'production_line';
    case Shift = 'shift';
    case Product = 'product';
    case ProductFamily = 'product_family';

    public function label(): string
    {
        return match ($this) {
            self::ProductionLine => 'Production line',
            self::Shift => 'Shift',
            self::Product => 'Product',
            self::ProductFamily => 'Product family',
        };
    }
}
