<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\ProductionBreakdownReport;
use App\DTOs\Analytics\ProductionMetricRow;
use App\Enums\Analytics\ProductionBreakdownDimension;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ProductionBreakdownService
{
    public function __construct(
        private readonly ProductionAnalyticsRepositoryInterface $repository,
        private readonly KpiFormulaService $formulas,
    ) {
    }

    public function build(
        AnalyticsFilter $filter
    ): ProductionBreakdownReport {
        $daily = $this->mergeRows(
            targets:
                $this->repository
                    ->dailyScheduledTargets($filter),
            actuals:
                $this->repository
                    ->dailyProductionMetrics($filter),
            identity:
                static fn (object $row): string =>
                    (string) ($row->metric_key ?? ''),
            label:
                static fn (object $row): string =>
                    (string) ($row->metric_key ?? ''),
        );

        $byLine = $this->dimensionRows(
            filter: $filter,
            dimension:
                ProductionBreakdownDimension::ProductionLine
        );

        $byShift = $this->dimensionRows(
            filter: $filter,
            dimension:
                ProductionBreakdownDimension::Shift
        );

        $byProduct = $this->dimensionRows(
            filter: $filter,
            dimension:
                ProductionBreakdownDimension::Product
        );

        $byProductFamily = $this->dimensionRows(
            filter: $filter,
            dimension:
                ProductionBreakdownDimension::ProductFamily
        );

        return new ProductionBreakdownReport(
            filter: $filter,
            generatedAt: CarbonImmutable::now('UTC'),
            dailyTrend: $daily,
            weeklyTrend:
                $this->rollUpTrend(
                    rows: $daily,
                    timezone: $filter->timezone,
                    period: 'week'
                ),
            monthlyTrend:
                $this->rollUpTrend(
                    rows: $daily,
                    timezone: $filter->timezone,
                    period: 'month'
                ),
            byProductionLine: $byLine,
            byShift: $byShift,
            byProduct: $byProduct,
            byProductFamily: $byProductFamily,
            bestLinesByUnit:
                $this->rankLines(
                    rows: $byLine,
                    descending: true
                ),
            lowestLinesByUnit:
                $this->rankLines(
                    rows: $byLine,
                    descending: false
                ),
        );
    }

    /**
     * @return list<ProductionMetricRow>
     */
    private function dimensionRows(
        AnalyticsFilter $filter,
        ProductionBreakdownDimension $dimension
    ): array {
        return $this->mergeRows(
            targets:
                $this->repository
                    ->scheduledTargetBreakdown(
                        $filter,
                        $dimension
                    ),
            actuals:
                $this->repository
                    ->productionBreakdown(
                        $filter,
                        $dimension
                    ),
            identity:
                fn (object $row): string =>
                    $this->normalizedLabel(
                        $row->dimension_label
                        ?? null
                    ),
            label:
                static fn (object $row): string =>
                    trim(
                        (string) (
                            $row->dimension_label
                            ?? 'Unknown'
                        )
                    ),
        );
    }

    /**
     * @param list<object> $targets
     * @param list<object> $actuals
     * @param callable(object):string $identity
     * @param callable(object):string $label
     *
     * @return list<ProductionMetricRow>
     */
    private function mergeRows(
        array $targets,
        array $actuals,
        callable $identity,
        callable $label
    ): array {
        /**
         * @var array<string, array{
         *     key:string,
         *     label:string,
         *     quantity_unit:string,
         *     target_count:int,
         *     record_count:int,
         *     validated_record_count:int,
         *     provisional_record_count:int,
         *     target_quantity:int,
         *     actual_quantity:int,
         *     good_quantity:int,
         *     rejected_quantity:int,
         *     runtime_minutes:int,
         *     downtime_minutes:int
         * }> $accumulators
         */
        $accumulators = [];

        foreach ($targets as $row) {
            $unit = $this->normalizeUnit(
                $row->quantity_unit
                ?? null
            );

            $rowIdentity = $identity($row);
            $key = $rowIdentity.'|'.$unit;

            $accumulators[$key] ??=
                $this->emptyAccumulator(
                    key: $rowIdentity,
                    label: $label($row),
                    quantityUnit: $unit
                );

            $accumulators[$key]['target_count'] +=
                (int) (
                    $row->target_count
                    ?? $row->target_order_count
                    ?? 0
                );

            $accumulators[$key]['target_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->target_quantity
                    ?? 0
                );
        }

        foreach ($actuals as $row) {
            $unit = $this->normalizeUnit(
                $row->quantity_unit
                ?? null
            );

            $rowIdentity = $identity($row);
            $key = $rowIdentity.'|'.$unit;

            $accumulators[$key] ??=
                $this->emptyAccumulator(
                    key: $rowIdentity,
                    label: $label($row),
                    quantityUnit: $unit
                );

            $accumulators[$key]['record_count'] +=
                (int) ($row->record_count ?? 0);

            $accumulators[$key]['validated_record_count'] +=
                (int) (
                    $row->validated_record_count
                    ?? 0
                );

            $accumulators[$key]['provisional_record_count'] +=
                (int) (
                    $row->provisional_record_count
                    ?? 0
                );

            $accumulators[$key]['actual_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->actual_quantity
                    ?? 0
                );

            $accumulators[$key]['good_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->good_quantity
                    ?? 0
                );

            $accumulators[$key]['rejected_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->rejected_quantity
                    ?? 0
                );

            $accumulators[$key]['runtime_minutes'] +=
                (int) ($row->runtime_minutes ?? 0);

            $accumulators[$key]['downtime_minutes'] +=
                (int) ($row->downtime_minutes ?? 0);
        }

        $rows = array_map(
            fn (array $values): ProductionMetricRow =>
                $this->metricRow($values),
            array_values($accumulators)
        );

        usort(
            $rows,
            static function (
                ProductionMetricRow $left,
                ProductionMetricRow $right
            ): int {
                $labelComparison = strnatcasecmp(
                    $left->label,
                    $right->label
                );

                return $labelComparison !== 0
                    ? $labelComparison
                    : strnatcasecmp(
                        $left->quantityUnit,
                        $right->quantityUnit
                    );
            }
        );

        return $rows;
    }

    /**
     * @param list<ProductionMetricRow> $rows
     *
     * @return list<ProductionMetricRow>
     */
    private function rollUpTrend(
        array $rows,
        string $timezone,
        string $period
    ): array {
        /**
         * @var array<string, array{
         *     key:string,
         *     label:string,
         *     quantity_unit:string,
         *     target_count:int,
         *     record_count:int,
         *     validated_record_count:int,
         *     provisional_record_count:int,
         *     target_quantity:int,
         *     actual_quantity:int,
         *     good_quantity:int,
         *     rejected_quantity:int,
         *     runtime_minutes:int,
         *     downtime_minutes:int
         * }> $accumulators
         */
        $accumulators = [];

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse(
                $row->key,
                $timezone
            );

            if ($period === 'week') {
                $periodStart = $date->startOfWeek(
                    CarbonInterface::MONDAY
                );

                $periodKey = $periodStart
                    ->toDateString();

                $periodLabel = sprintf(
                    '%s-W%s',
                    $periodStart->format('o'),
                    $periodStart->format('W')
                );
            } else {
                $periodStart = $date->startOfMonth();
                $periodKey = $periodStart->format('Y-m');
                $periodLabel = $periodKey;
            }

            $key = $periodKey.'|'.$row->quantityUnit;

            $accumulators[$key] ??=
                $this->emptyAccumulator(
                    key: $periodKey,
                    label: $periodLabel,
                    quantityUnit:
                        $row->quantityUnit
                );

            $accumulators[$key]['target_count'] +=
                $row->targetCount;

            $accumulators[$key]['record_count'] +=
                $row->recordCount;

            $accumulators[$key]['validated_record_count'] +=
                $row->validatedRecordCount;

            $accumulators[$key]['provisional_record_count'] +=
                $row->provisionalRecordCount;

            $accumulators[$key]['target_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->targetQuantity
                );

            $accumulators[$key]['actual_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->actualQuantity
                );

            $accumulators[$key]['good_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->goodQuantity
                );

            $accumulators[$key]['rejected_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->rejectedQuantity
                );

            $accumulators[$key]['runtime_minutes'] +=
                $row->runtimeMinutes;

            $accumulators[$key]['downtime_minutes'] +=
                $row->downtimeMinutes;
        }

        $result = array_map(
            fn (array $values): ProductionMetricRow =>
                $this->metricRow($values),
            array_values($accumulators)
        );

        usort(
            $result,
            static fn (
                ProductionMetricRow $left,
                ProductionMetricRow $right
            ): int => [
                $left->key,
                $left->quantityUnit,
            ] <=> [
                $right->key,
                $right->quantityUnit,
            ]
        );

        return $result;
    }

    /**
     * @param list<ProductionMetricRow> $rows
     *
     * @return list<ProductionMetricRow>
     */
    private function rankLines(
        array $rows,
        bool $descending
    ): array {
        $byUnit = [];

        foreach ($rows as $row) {
            if (
                ! $row->hasActualProduction()
                || ! $row->hasTarget()
                || $row->achievementPercentage === null
            ) {
                continue;
            }

            $byUnit[$row->quantityUnit][] = $row;
        }

        ksort($byUnit, SORT_NATURAL | SORT_FLAG_CASE);

        $ranked = [];

        foreach ($byUnit as $unitRows) {
            usort(
                $unitRows,
                function (
                    ProductionMetricRow $left,
                    ProductionMetricRow $right
                ) use ($descending): int {
                    $achievement =
                        ($left->achievementPercentage ?? 0)
                        <=>
                        ($right->achievementPercentage ?? 0);

                    if ($achievement !== 0) {
                        return $descending
                            ? -$achievement
                            : $achievement;
                    }

                    $leftRejection =
                        $left->rejectionPercentage
                        ?? PHP_FLOAT_MAX;

                    $rightRejection =
                        $right->rejectionPercentage
                        ?? PHP_FLOAT_MAX;

                    $rejection =
                        $leftRejection
                        <=>
                        $rightRejection;

                    if ($rejection !== 0) {
                        return $descending
                            ? $rejection
                            : -$rejection;
                    }

                    $actual =
                        $this->formulas->toMilliUnits(
                            $left->actualQuantity
                        )
                        <=>
                        $this->formulas->toMilliUnits(
                            $right->actualQuantity
                        );

                    if ($actual !== 0) {
                        return $descending
                            ? -$actual
                            : $actual;
                    }

                    return strnatcasecmp(
                        $left->label,
                        $right->label
                    );
                }
            );

            if ($unitRows !== []) {
                $ranked[] = $unitRows[0];
            }
        }

        return $ranked;
    }

    /**
     * @param array{
     *     key:string,
     *     label:string,
     *     quantity_unit:string,
     *     target_count:int,
     *     record_count:int,
     *     validated_record_count:int,
     *     provisional_record_count:int,
     *     target_quantity:int,
     *     actual_quantity:int,
     *     good_quantity:int,
     *     rejected_quantity:int,
     *     runtime_minutes:int,
     *     downtime_minutes:int
     * } $values
     */
    private function metricRow(
        array $values
    ): ProductionMetricRow {
        $hasActual =
            $values['record_count'] > 0;

        return new ProductionMetricRow(
            key: $values['key'],
            label: $values['label'],
            quantityUnit:
                $values['quantity_unit'],
            targetCount:
                $values['target_count'],
            recordCount:
                $values['record_count'],
            validatedRecordCount:
                $values['validated_record_count'],
            provisionalRecordCount:
                $values['provisional_record_count'],
            targetQuantity:
                $this->formulas->fromMilliUnits(
                    $values['target_quantity']
                ),
            actualQuantity:
                $this->formulas->fromMilliUnits(
                    $values['actual_quantity']
                ),
            goodQuantity:
                $this->formulas->fromMilliUnits(
                    $values['good_quantity']
                ),
            rejectedQuantity:
                $this->formulas->fromMilliUnits(
                    $values['rejected_quantity']
                ),
            runtimeMinutes:
                $values['runtime_minutes'],
            downtimeMinutes:
                $values['downtime_minutes'],
            achievementPercentage:
                $hasActual
                    ? $this->formulas->percentage(
                        $values['actual_quantity'],
                        $values['target_quantity']
                    )
                    : null,
            rejectionPercentage:
                $this->formulas->percentage(
                    $values['rejected_quantity'],
                    $values['actual_quantity']
                ),
            qualityYieldPercentage:
                $this->formulas->percentage(
                    $values['good_quantity'],
                    $values['actual_quantity']
                ),
            goodOutputEfficiencyPercentage:
                $hasActual
                    ? $this->formulas->percentage(
                        $values['good_quantity'],
                        $values['target_quantity']
                    )
                    : null,
            averageProductionRatePerHour:
                $this->formulas->quantityPerHour(
                    $values['actual_quantity'],
                    $values['runtime_minutes']
                ),
            observedUtilizationPercentage:
                $this->formulas
                    ->observedUtilization(
                        $values['runtime_minutes'],
                        $values['downtime_minutes']
                    ),
        );
    }

    /**
     * @return array{
     *     key:string,
     *     label:string,
     *     quantity_unit:string,
     *     target_count:int,
     *     record_count:int,
     *     validated_record_count:int,
     *     provisional_record_count:int,
     *     target_quantity:int,
     *     actual_quantity:int,
     *     good_quantity:int,
     *     rejected_quantity:int,
     *     runtime_minutes:int,
     *     downtime_minutes:int
     * }
     */
    private function emptyAccumulator(
        string $key,
        string $label,
        string $quantityUnit
    ): array {
        return [
            'key' => $key,
            'label' => $label === ''
                ? 'Unknown'
                : $label,
            'quantity_unit' => $quantityUnit,
            'target_count' => 0,
            'record_count' => 0,
            'validated_record_count' => 0,
            'provisional_record_count' => 0,
            'target_quantity' => 0,
            'actual_quantity' => 0,
            'good_quantity' => 0,
            'rejected_quantity' => 0,
            'runtime_minutes' => 0,
            'downtime_minutes' => 0,
        ];
    }

    private function normalizeUnit(
        mixed $unit
    ): string {
        if (! is_string($unit)) {
            return 'unknown';
        }

        $unit = mb_strtolower(trim($unit));

        return $unit === ''
            ? 'unknown'
            : $unit;
    }

    private function normalizedLabel(
        mixed $label
    ): string {
        if (! is_string($label)) {
            return 'unknown';
        }

        $label = preg_replace(
            '/\s+/u',
            ' ',
            trim($label)
        );

        $label = is_string($label)
            ? $label
            : '';

        return $label === ''
            ? 'unknown'
            : mb_strtolower($label);
    }
}
