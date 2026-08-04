<?php

namespace App\Services\Analytics;

use InvalidArgumentException;

final class KpiFormulaService
{
    private const QUANTITY_SCALE = 1000;

    public function toMilliUnits(
        string|int|float|null $quantity
    ): int {
        if ($quantity === null || $quantity === '') {
            return 0;
        }

        if (! is_numeric($quantity)) {
            throw new InvalidArgumentException(
                'KPI quantities must be numeric.'
            );
        }

        return (int) round(
            ((float) $quantity)
            * self::QUANTITY_SCALE
        );
    }

    public function fromMilliUnits(
        int $quantity
    ): string {
        return number_format(
            $quantity / self::QUANTITY_SCALE,
            3,
            '.',
            ''
        );
    }

    public function percentage(
        int $numerator,
        int $denominator,
        int $precision = 2
    ): ?float {
        if ($denominator === 0) {
            return null;
        }

        return round(
            ($numerator / $denominator) * 100,
            $precision
        );
    }

    public function quantityPerHour(
        int $quantityMilliUnits,
        int $runtimeMinutes
    ): ?string {
        if ($runtimeMinutes === 0) {
            return null;
        }

        $rateMilliUnits = (int) round(
            ($quantityMilliUnits * 60)
            / $runtimeMinutes
        );

        return $this->fromMilliUnits(
            $rateMilliUnits
        );
    }

    public function observedUtilization(
        int $runtimeMinutes,
        int $downtimeMinutes
    ): ?float {
        return $this->percentage(
            $runtimeMinutes,
            $runtimeMinutes + $downtimeMinutes
        );
    }
}
