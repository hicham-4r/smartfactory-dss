<?php

namespace App\Services\AI\Inference;

use App\Exceptions\AI\InferenceFeaturePreparationException;
use Carbon\CarbonImmutable;

class InferenceFeatureCalculator
{
    private const FAILURE_TOKENS = [
        'fault',
        'failure',
        'breakdown',
        'panne',
        'unplanned',
    ];

    private const FAULT_STATUSES = [
        'fault',
        'failed',
        'failure',
        'breakdown',
        'down',
    ];

    private const RUNNING_STATUSES = [
        'running',
        'active',
        'operational',
        'producing',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function forecast(
        array $rows,
        CarbonImmutable $predictionDate,
        string $productionLineCode,
        string $quantityUnit,
    ): array {
        $featureDate = $predictionDate
            ->startOfDay()
            ->subDay();

        $daily = [];

        foreach ($rows as $row) {
            if (
                (string) ($row['production_line_code'] ?? '')
                    !== $productionLineCode
                || (string) ($row['quantity_unit'] ?? '')
                    !== $quantityUnit
            ) {
                continue;
            }

            $date = $this->date(
                $row['production_date'] ?? null,
                'A production record has an invalid production date.'
            );

            if ($date->greaterThan($featureDate)) {
                continue;
            }

            $key = $date->toDateString();

            $daily[$key] ??= [
                'good' => 0.0,
                'produced' => 0.0,
                'target' => 0.0,
                'rejected' => 0.0,
                'runtime' => 0,
                'downtime' => 0,
            ];

            $daily[$key]['good'] +=
                $this->nonNegativeFloat($row['good_quantity'] ?? 0);
            $daily[$key]['produced'] +=
                $this->nonNegativeFloat($row['produced_quantity'] ?? 0);
            $daily[$key]['target'] +=
                $this->nonNegativeFloat($row['target_quantity'] ?? 0);
            $daily[$key]['rejected'] +=
                $this->nonNegativeFloat($row['rejected_quantity'] ?? 0);
            $daily[$key]['runtime'] +=
                $this->nonNegativeInteger($row['runtime_minutes'] ?? 0);
            $daily[$key]['downtime'] +=
                $this->nonNegativeInteger($row['downtime_minutes'] ?? 0);
        }

        ksort($daily);

        $featureKey = $featureDate->toDateString();

        if (! array_key_exists($featureKey, $daily)) {
            throw InferenceFeaturePreparationException::insufficientHistory(
                "No validated production exists for {$featureKey}. "
                .'Choose the day immediately after a completed production day.'
            );
        }

        $historyDates = array_values(
            array_filter(
                array_keys($daily),
                static fn (string $date): bool => $date <= $featureKey
            )
        );

        $minimumObservations = max(
            2,
            (int) config(
                'ai-automatic-inference.minimum_forecast_observations',
                2
            )
        );

        if (count($historyDates) < $minimumObservations) {
            throw InferenceFeaturePreparationException::insufficientHistory(
                "At least {$minimumObservations} validated production days "
                .'are required for this line and quantity unit.'
            );
        }

        $windowStart = $featureDate
            ->subDays(6)
            ->toDateString();

        $windowDates = array_values(
            array_filter(
                $historyDates,
                static fn (string $date): bool => $date >= $windowStart
                    && $date <= $featureKey
            )
        );

        if (count($windowDates) < $minimumObservations) {
            throw InferenceFeaturePreparationException::insufficientHistory(
                "The seven-day window needs at least {$minimumObservations} "
                .'validated production observations.'
            );
        }

        $goodWindow = array_map(
            fn (string $date): float => (float) $daily[$date]['good'],
            $windowDates
        );

        $current = $daily[$featureKey];
        $lagSevenKey = $featureDate
            ->subDays(7)
            ->toDateString();

        return [
            'prediction_date' => $predictionDate->toDateString(),
            'features' => [
                'production_line_code' => $productionLineCode,
                'quantity_unit' => $quantityUnit,
                'days_of_history' => count($historyDates),
                'rolling_observation_count_7d' => count($windowDates),
                'day_of_week' => $featureDate->dayOfWeekIso - 1,
                'month' => $featureDate->month,
                'good_quantity_lag_1d' => (float) $current['good'],
                'good_quantity_lag_7d' => isset($daily[$lagSevenKey])
                        ? (float) $daily[$lagSevenKey]['good']
                        : null,
                'good_quantity_mean_7d' => array_sum($goodWindow)
                    / count($goodWindow),
                'good_quantity_min_7d' => min($goodWindow),
                'good_quantity_max_7d' => max($goodWindow),
                'produced_quantity_lag_1d' => (float) $current['produced'],
                'target_quantity_lag_1d' => (float) $current['target'],
                'runtime_minutes_lag_1d' => (int) $current['runtime'],
                'downtime_minutes_lag_1d' => (int) $current['downtime'],
                'rejection_rate_lag_1d' => $this->ratio(
                    (float) $current['rejected'],
                    (float) $current['produced']
                ),
                'achievement_rate_lag_1d' => $this->ratio(
                    (float) $current['produced'],
                    (float) $current['target']
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function anomaly(array $row): array
    {
        $target =
            $this->nonNegativeFloat($row['target_quantity'] ?? 0);
        $produced =
            $this->nonNegativeFloat($row['produced_quantity'] ?? 0);
        $good =
            $this->nonNegativeFloat($row['good_quantity'] ?? 0);
        $rejected =
            $this->nonNegativeFloat($row['rejected_quantity'] ?? 0);
        $runtime =
            $this->nonNegativeInteger($row['runtime_minutes'] ?? 0);
        $downtime =
            $this->nonNegativeInteger($row['downtime_minutes'] ?? 0);

        $eventTime = CarbonImmutable::parse(
            (string) ($row['started_at_utc'] ?? ''),
            'UTC'
        )->utc();

        return [
            'event_time_utc' => $eventTime->toIso8601String(),
            'features' => [
                'production_line_code' => $this->requiredText(
                    $row['production_line_code'] ?? null,
                    'The production line code is missing.'
                ),
                'product_family_code' => $this->requiredText(
                    $row['product_family_code'] ?? null,
                    'The product family code is missing.'
                ),
                'product_code' => $this->requiredText(
                    $row['product_code'] ?? null,
                    'The product code is missing.'
                ),
                'shift_code' => $this->requiredText(
                    $row['shift_code'] ?? null,
                    'The shift code is missing.'
                ),
                'quantity_unit' => $this->requiredText(
                    $row['quantity_unit'] ?? null,
                    'The quantity unit is missing.'
                ),
                'production_order_priority' => $this->nonNegativeInteger(
                    $row['production_order_priority'] ?? 0
                ),
                'target_quantity' => $target,
                'produced_quantity' => $produced,
                'good_quantity' => $good,
                'rejected_quantity' => $rejected,
                'runtime_minutes' => $runtime,
                'downtime_minutes' => $downtime,
                'achievement_ratio' => $this->ratio($produced, $target),
                'rejection_ratio' => $this->ratio($rejected, $produced),
                'good_yield_ratio' => $this->ratio($good, $produced),
                'throughput_per_hour' => $runtime > 0
                        ? ($produced * 60) / $runtime
                        : null,
                'downtime_ratio' => ($runtime + $downtime) > 0
                        ? $downtime / ($runtime + $downtime)
                        : null,
                'is_validated' => (bool) ($row['is_validated'] ?? false),
            ],
        ];
    }

    /**
     * @param array{
     *   machine: array<string, mixed>,
     *   statuses: list<array<string, mixed>>,
     *   downtime: list<array<string, mixed>>,
     *   maintenance: list<array<string, mixed>>
     * } $context
     * @return array<string, mixed>
     */
    public function maintenance(
        array $context,
        CarbonImmutable $predictionDate,
    ): array {
        $predictionDate = $predictionDate->startOfDay();
        $historySevenStart = $predictionDate->subDays(7);
        $historyThirtyStart = $predictionDate->subDays(30);

        $machine = $context['machine'];
        $statuses = $context['statuses'];
        $downtime = $context['downtime'];
        $maintenance = $context['maintenance'];

        $observedDates = [];

        foreach ($statuses as $row) {
            $observedDates[] = $this->date(
                $row['occurred_at_utc'] ?? null,
                'A machine-status event has an invalid date.'
            );
        }

        foreach ($downtime as $row) {
            $observedDates[] = $this->date(
                $row['started_at_utc'] ?? null,
                'A downtime event has an invalid date.'
            );
        }

        foreach ($maintenance as $row) {
            $observedDates[] = $this->date(
                $row['scheduled_at_utc'] ?? null,
                'A maintenance event has an invalid scheduled date.'
            );
        }

        if ($observedDates === []) {
            throw InferenceFeaturePreparationException::insufficientHistory(
                'No machine-status, downtime, or maintenance history exists '
                .'before the selected prediction date.'
            );
        }

        usort(
            $observedDates,
            static fn (
                CarbonImmutable $left,
                CarbonImmutable $right
            ): int => $left->getTimestamp() <=> $right->getTimestamp()
        );

        $daysObserved = (int) max(
            0,
            $observedDates[0]->diffInDays($predictionDate, false)
        );

        $minimumDays = max(
            1,
            (int) config(
                'ai-automatic-inference.minimum_maintenance_days_observed',
                30
            )
        );

        if ($daysObserved < $minimumDays) {
            throw InferenceFeaturePreparationException::insufficientHistory(
                "At least {$minimumDays} days of machine history are required."
            );
        }

        $statusWindow = array_values(
            array_filter(
                $statuses,
                fn (array $row): bool => $this->insideWindow(
                    $row['occurred_at_utc'] ?? null,
                    $historySevenStart,
                    $predictionDate
                )
            )
        );

        $downtimeWindow = array_values(
            array_filter(
                $downtime,
                fn (array $row): bool => $this->insideWindow(
                    $row['started_at_utc'] ?? null,
                    $historySevenStart,
                    $predictionDate
                )
            )
        );

        $maintenanceWindow = array_values(
            array_filter(
                $maintenance,
                fn (array $row): bool => $this->insideWindow(
                    $row['scheduled_at_utc'] ?? null,
                    $historyThirtyStart,
                    $predictionDate
                )
            )
        );

        $previousFailureDates = [];

        foreach ($downtime as $row) {
            if ($this->isFailureDowntime($row)) {
                $previousFailureDates[] = $this->date(
                    $row['started_at_utc'] ?? null,
                    'A downtime event has an invalid date.'
                );
            }
        }

        foreach ($statuses as $row) {
            if ($this->isFaultStatus($row)) {
                $previousFailureDates[] = $this->date(
                    $row['occurred_at_utc'] ?? null,
                    'A machine-status event has an invalid date.'
                );
            }
        }

        $previousMaintenanceDates = array_map(
            fn (array $row): CarbonImmutable => $this->date(
                $row['scheduled_at_utc'] ?? null,
                'A maintenance event has an invalid scheduled date.'
            ),
            $maintenance
        );

        return [
            'prediction_date' => $predictionDate->toDateString(),
            'features' => [
                'production_line_code' => $this->requiredText(
                    $machine['production_line_code'] ?? null,
                    'The machine production line is missing.'
                ),
                'machine_code' => $this->requiredText(
                    $machine['machine_code'] ?? null,
                    'The machine code is missing.'
                ),
                'machine_type' => $this->requiredText(
                    $machine['machine_type'] ?? null,
                    'The machine type is missing.'
                ),
                'is_critical' => $statuses !== []
                    && (bool) ($machine['is_critical'] ?? false),
                'days_observed' => $daysObserved,
                'status_event_count_7d' => count($statusWindow),
                'fault_status_event_count_7d' => count(
                    array_filter(
                        $statusWindow,
                        fn (array $row): bool => $this->isFaultStatus($row)
                    )
                ),
                'running_minutes_7d' => $this->sumMinutes(
                    $statusWindow,
                    'duration_minutes',
                    fn (array $row): bool => $this->isRunningStatus($row)
                ),
                'fault_minutes_7d' => $this->sumMinutes(
                    $statusWindow,
                    'duration_minutes',
                    fn (array $row): bool => $this->isFaultStatus($row)
                ),
                'downtime_event_count_7d' => count($downtimeWindow),
                'unplanned_downtime_event_count_7d' => count(
                    array_filter(
                        $downtimeWindow,
                        fn (array $row): bool => $this->isFailureDowntime($row)
                    )
                ),
                'total_downtime_minutes_7d' => $this->sumMinutes(
                    $downtimeWindow,
                    'duration_minutes'
                ),
                'unplanned_downtime_minutes_7d' => $this->sumMinutes(
                    $downtimeWindow,
                    'duration_minutes',
                    fn (array $row): bool => $this->isFailureDowntime($row)
                ),
                'maintenance_event_count_30d' => count($maintenanceWindow),
                'preventive_maintenance_count_30d' => count(
                    array_filter(
                        $maintenanceWindow,
                        fn (array $row): bool => $this->isPreventive($row)
                    )
                ),
                'corrective_maintenance_count_30d' => count(
                    array_filter(
                        $maintenanceWindow,
                        fn (array $row): bool => $this->isCorrective($row)
                    )
                ),
                'maintenance_downtime_minutes_30d' => $this->sumMinutes(
                    $maintenanceWindow,
                    'downtime_minutes'
                ),
                'days_since_last_failure' => $this->daysSince(
                    $previousFailureDates,
                    $predictionDate
                ),
                'days_since_last_maintenance' => $this->daysSince(
                    $previousMaintenanceDates,
                    $predictionDate
                ),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sumMinutes(
        array $rows,
        string $field,
        ?callable $filter = null,
    ): int {
        $total = 0;

        foreach ($rows as $row) {
            if ($filter !== null && ! $filter($row)) {
                continue;
            }

            $total += $this->nonNegativeInteger(
                $row[$field] ?? 0
            );
        }

        return $total;
    }

    /**
     * @param  list<CarbonImmutable>  $dates
     */
    private function daysSince(
        array $dates,
        CarbonImmutable $predictionDate,
    ): ?int {
        $eligible = array_values(
            array_filter(
                $dates,
                static fn (CarbonImmutable $date): bool => $date->lessThan($predictionDate)
            )
        );

        if ($eligible === []) {
            return null;
        }

        usort(
            $eligible,
            static fn (
                CarbonImmutable $left,
                CarbonImmutable $right
            ): int => $right->getTimestamp() <=> $left->getTimestamp()
        );

        return (int) max(
            0,
            $eligible[0]->diffInDays($predictionDate, false)
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isFailureDowntime(array $row): bool
    {
        $category = strtolower(
            trim((string) ($row['category'] ?? ''))
        );
        $type = strtolower(
            trim((string) ($row['downtime_type'] ?? ''))
        );
        $severity = strtolower(
            trim((string) ($row['severity'] ?? ''))
        );

        return $category === 'unplanned'
            || $severity === 'critical'
            || $this->containsToken(
                $type,
                self::FAILURE_TOKENS
            );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isFaultStatus(array $row): bool
    {
        $status = strtolower(
            trim((string) ($row['status'] ?? ''))
        );

        return in_array(
            $status,
            self::FAULT_STATUSES,
            true
        ) || $this->containsToken(
            $status,
            self::FAILURE_TOKENS
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isRunningStatus(array $row): bool
    {
        return in_array(
            strtolower(
                trim((string) ($row['status'] ?? ''))
            ),
            self::RUNNING_STATUSES,
            true
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isPreventive(array $row): bool
    {
        $type = strtolower(
            trim((string) ($row['maintenance_type'] ?? ''))
        );

        return in_array(
            $type,
            [
                'preventive',
                'préventive',
                'preventif',
                'préventif',
            ],
            true
        ) || str_contains($type, 'prevent');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isCorrective(array $row): bool
    {
        $type = strtolower(
            trim((string) ($row['maintenance_type'] ?? ''))
        );

        return in_array(
            $type,
            [
                'corrective',
                'correctif',
                'curative',
                'repair',
            ],
            true
        ) || str_contains($type, 'correct');
    }

    /**
     * @param  list<string>  $tokens
     */
    private function containsToken(
        string $value,
        array $tokens,
    ): bool {
        foreach ($tokens as $token) {
            if (str_contains($value, $token)) {
                return true;
            }
        }

        return false;
    }

    private function insideWindow(
        mixed $value,
        CarbonImmutable $start,
        CarbonImmutable $endExclusive,
    ): bool {
        $date = $this->date(
            $value,
            'An operational event has an invalid date.'
        );

        return $date->greaterThanOrEqualTo($start)
            && $date->lessThan($endExclusive);
    }

    private function date(
        mixed $value,
        string $message,
    ): CarbonImmutable {
        try {
            if (
                ! is_string($value)
                || trim($value) === ''
            ) {
                throw new \InvalidArgumentException;
            }

            return CarbonImmutable::parse(
                $value,
                'UTC'
            )
                ->utc()
                ->startOfDay();
        } catch (\Throwable $exception) {
            throw InferenceFeaturePreparationException::invalidSelection(
                $message
            );
        }
    }

    private function requiredText(
        mixed $value,
        string $message,
    ): string {
        if (! is_string($value) && ! is_numeric($value)) {
            throw InferenceFeaturePreparationException::invalidSelection(
                $message
            );
        }

        $text = trim((string) $value);

        if ($text === '') {
            throw InferenceFeaturePreparationException::invalidSelection(
                $message
            );
        }

        return mb_substr($text, 0, 100);
    }

    private function nonNegativeFloat(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return max(0.0, (float) $value);
    }

    private function nonNegativeInteger(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    private function ratio(
        float $numerator,
        float $denominator,
    ): ?float {
        if ($denominator <= 0.0) {
            return null;
        }

        return $numerator / $denominator;
    }
}
