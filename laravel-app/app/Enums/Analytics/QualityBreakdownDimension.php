<?php

namespace App\Enums\Analytics;

enum QualityBreakdownDimension: string
{
    case ProductionLine = 'production_line';
    case Product = 'product';
    case ProductFamily = 'product_family';
}
