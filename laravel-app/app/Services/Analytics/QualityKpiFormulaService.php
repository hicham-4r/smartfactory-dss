<?php

namespace App\Services\Analytics;

final class QualityKpiFormulaService
{
    public function percentage(
        int|float|string $numerator,
        int|float|string $denominator,
        int $precision = 2,
    ): ?float {
        $denominator = (float) $denominator;

        if ($denominator <= 0) {
            return null;
        }

        return round(
            ((float) $numerator / $denominator) * 100,
            $precision
        );
    }

    public function per100(
        int|float|string $count,
        int|float|string $population,
        int $precision = 2,
    ): ?float {
        return $this->percentage(
            numerator: $count,
            denominator: $population,
            precision: $precision,
        );
    }

    public function decimalString(
        int|float|string|null $value,
        int $precision = 3,
    ): string {
        return number_format(
            (float) ($value ?? 0),
            $precision,
            '.',
            ''
        );
    }
}
