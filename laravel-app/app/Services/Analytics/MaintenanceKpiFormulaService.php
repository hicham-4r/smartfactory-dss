<?php

namespace App\Services\Analytics;

final class MaintenanceKpiFormulaService
{
    public function percentage(
        int $numerator,
        int $denominator,
        int $precision = 2
    ): ?float {
        if ($denominator <= 0) {
            return null;
        }

        return round(
            ($numerator / $denominator) * 100,
            $precision
        );
    }

    public function averageMinutes(
        int $totalMinutes,
        int $occurrenceCount,
        int $precision = 2
    ): ?float {
        if ($occurrenceCount <= 0) {
            return null;
        }

        return round(
            $totalMinutes / $occurrenceCount,
            $precision
        );
    }

    public function mtbfMinutes(
        int $runningMinutes,
        int $failureCount,
        int $precision = 2
    ): ?float {
        return $this->averageMinutes(
            totalMinutes: $runningMinutes,
            occurrenceCount: $failureCount,
            precision: $precision,
        );
    }

    public function failuresPer100RunningHours(
        int $failureCount,
        int $runningMinutes,
        int $precision = 2
    ): ?float {
        if ($runningMinutes <= 0) {
            return null;
        }

        return round(
            ($failureCount * 6000)
            / $runningMinutes,
            $precision
        );
    }
}
