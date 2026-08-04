<?php

namespace App\DTOs\Analytics;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class ProductionBreakdownReport implements JsonSerializable
{
    /**
     * @param list<ProductionMetricRow> $dailyTrend
     * @param list<ProductionMetricRow> $weeklyTrend
     * @param list<ProductionMetricRow> $monthlyTrend
     * @param list<ProductionMetricRow> $byProductionLine
     * @param list<ProductionMetricRow> $byShift
     * @param list<ProductionMetricRow> $byProduct
     * @param list<ProductionMetricRow> $byProductFamily
     * @param list<ProductionMetricRow> $bestLinesByUnit
     * @param list<ProductionMetricRow> $lowestLinesByUnit
     */
    public function __construct(
        public AnalyticsFilter $filter,
        public CarbonImmutable $generatedAt,
        public array $dailyTrend,
        public array $weeklyTrend,
        public array $monthlyTrend,
        public array $byProductionLine,
        public array $byShift,
        public array $byProduct,
        public array $byProductFamily,
        public array $bestLinesByUnit,
        public array $lowestLinesByUnit,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->dailyTrend === []
            && $this->byProductionLine === []
            && $this->byShift === []
            && $this->byProduct === []
            && $this->byProductFamily === [];
    }

    public function hasMixedUnits(): bool
    {
        $units = [];

        foreach (
            [
                ...$this->dailyTrend,
                ...$this->byProductionLine,
                ...$this->byShift,
                ...$this->byProduct,
                ...$this->byProductFamily,
            ] as $row
        ) {
            $units[$row->quantityUnit] = true;
        }

        return count($units) > 1;
    }

    public function lineRankingBasis(): string
    {
        return 'Lines are ranked within each quantity unit by target achievement. Ties prefer lower rejection, then higher actual output.';
    }

    public function shiftTargetCaution(): string
    {
        return 'Shift target values use planned batch quantities for batches that contain eligible records from that shift. A batch spanning multiple shifts can therefore appear in more than one shift and shift targets must not be summed across rows.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $serialize = static fn (
            array $rows
        ): array => array_map(
            static fn (
                ProductionMetricRow $row
            ): array => $row->toArray(),
            $rows
        );

        return [
            'filter' => $this->filter->toArray(),
            'generated_at' => $this->generatedAt
                ->utc()
                ->toIso8601String(),
            'is_empty' => $this->isEmpty(),
            'has_mixed_units' => $this->hasMixedUnits(),
            'line_ranking_basis' => $this->lineRankingBasis(),
            'shift_target_caution' => $this->shiftTargetCaution(),
            'daily_trend' => $serialize($this->dailyTrend),
            'weekly_trend' => $serialize($this->weeklyTrend),
            'monthly_trend' => $serialize($this->monthlyTrend),
            'by_production_line' =>
                $serialize($this->byProductionLine),
            'by_shift' => $serialize($this->byShift),
            'by_product' => $serialize($this->byProduct),
            'by_product_family' =>
                $serialize($this->byProductFamily),
            'best_lines_by_unit' =>
                $serialize($this->bestLinesByUnit),
            'lowest_lines_by_unit' =>
                $serialize($this->lowestLinesByUnit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
