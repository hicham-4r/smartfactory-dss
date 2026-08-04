<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\ProductionKpiUnitSummary;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;

final class ProductionKpiService
{
    public function __construct(
        private readonly ProductionAnalyticsRepositoryInterface $repository,
        private readonly KpiFormulaService $formulas,
    ) {
    }

    public function summarize(
        AnalyticsFilter $filter
    ): ProductionKpiSummary {
        /**
         * @var array<string, array{
         *     target_order_count: int,
         *     record_count: int,
         *     validated_record_count: int,
         *     provisional_record_count: int,
         *     target_quantity: int,
         *     actual_quantity: int,
         *     good_quantity: int,
         *     rejected_quantity: int,
         *     runtime_minutes: int,
         *     downtime_minutes: int
         * }> $byUnit
         */
        $byUnit = [];

        foreach (
            $this->repository
                ->scheduledTargetsByUnit($filter)
            as $row
        ) {
            $unit = $this->normalizeUnit(
                $row->quantity_unit
                ?? null
            );

            $byUnit[$unit] ??=
                $this->emptyAccumulator();

            $byUnit[$unit]['target_order_count'] +=
                (int) (
                    $row->target_order_count
                    ?? 0
                );

            $byUnit[$unit]['target_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->target_quantity
                    ?? 0
                );
        }

        foreach (
            $this->repository
                ->productionByUnit($filter)
            as $row
        ) {
            $unit = $this->normalizeUnit(
                $row->quantity_unit
                ?? null
            );

            $byUnit[$unit] ??=
                $this->emptyAccumulator();

            $byUnit[$unit]['record_count'] +=
                (int) (
                    $row->record_count
                    ?? 0
                );

            $byUnit[$unit]['validated_record_count'] +=
                (int) (
                    $row->validated_record_count
                    ?? 0
                );

            $byUnit[$unit]['provisional_record_count'] +=
                (int) (
                    $row->provisional_record_count
                    ?? 0
                );

            $byUnit[$unit]['actual_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->actual_quantity
                    ?? 0
                );

            $byUnit[$unit]['good_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->good_quantity
                    ?? 0
                );

            $byUnit[$unit]['rejected_quantity'] +=
                $this->formulas->toMilliUnits(
                    $row->rejected_quantity
                    ?? 0
                );

            $byUnit[$unit]['runtime_minutes'] +=
                (int) (
                    $row->runtime_minutes
                    ?? 0
                );

            $byUnit[$unit]['downtime_minutes'] +=
                (int) (
                    $row->downtime_minutes
                    ?? 0
                );
        }

        ksort($byUnit, SORT_NATURAL | SORT_FLAG_CASE);

        $units = [];
        $recordCount = 0;
        $validatedRecordCount = 0;
        $provisionalRecordCount = 0;
        $targetOrderCount = 0;
        $runtimeMinutes = 0;
        $downtimeMinutes = 0;

        foreach ($byUnit as $unit => $values) {
            $units[] = new ProductionKpiUnitSummary(
                quantityUnit: $unit,
                targetOrderCount:
                    $values['target_order_count'],
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
                    $values['record_count'] > 0
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

            $recordCount += $values['record_count'];
            $validatedRecordCount +=
                $values['validated_record_count'];
            $provisionalRecordCount +=
                $values['provisional_record_count'];
            $targetOrderCount +=
                $values['target_order_count'];
            $runtimeMinutes +=
                $values['runtime_minutes'];
            $downtimeMinutes +=
                $values['downtime_minutes'];
        }

        return new ProductionKpiSummary(
            filter: $filter,
            generatedAt: CarbonImmutable::now('UTC'),
            units: $units,
            recordCount: $recordCount,
            validatedRecordCount: $validatedRecordCount,
            provisionalRecordCount: $provisionalRecordCount,
            targetOrderCount: $targetOrderCount,
            runtimeMinutes: $runtimeMinutes,
            downtimeMinutes: $downtimeMinutes,
        );
    }

    /**
     * @return array{
     *     target_order_count: int,
     *     record_count: int,
     *     validated_record_count: int,
     *     provisional_record_count: int,
     *     target_quantity: int,
     *     actual_quantity: int,
     *     good_quantity: int,
     *     rejected_quantity: int,
     *     runtime_minutes: int,
     *     downtime_minutes: int
     * }
     */
    private function emptyAccumulator(): array
    {
        return [
            'target_order_count' => 0,
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

        $unit = mb_strtolower(
            trim($unit)
        );

        return $unit === ''
            ? 'unknown'
            : $unit;
    }
}
