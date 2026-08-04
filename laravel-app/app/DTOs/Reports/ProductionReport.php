<?php

namespace App\DTOs\Reports;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\ProductionBreakdownReport;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\ProductionMetricRow;
use App\Enums\Reports\ProductionReportType;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class ProductionReport implements JsonSerializable
{
    /**
     * @param array<string, string> $appliedFilters
     */
    public function __construct(
        public ProductionReportType $type,
        public string $title,
        public AnalyticsFilter $filter,
        public CarbonImmutable $generatedAt,
        public string $generatedByName,
        public string $generatedByEmail,
        public array $appliedFilters,
        public ProductionKpiSummary $summary,
        public ProductionBreakdownReport $breakdowns,
    ) {
    }

    /**
     * @return list<ProductionMetricRow>
     */
    public function primaryRows(): array
    {
        return match ($this->type) {
            ProductionReportType::Daily =>
                $this->breakdowns->dailyTrend,

            ProductionReportType::Weekly =>
                $this->breakdowns->weeklyTrend,

            ProductionReportType::Monthly,
            ProductionReportType::Executive =>
                $this->breakdowns->monthlyTrend,

            ProductionReportType::ProductionLine =>
                $this->breakdowns->byProductionLine,

            ProductionReportType::Product =>
                $this->breakdowns->byProduct,

            ProductionReportType::Shift =>
                $this->breakdowns->byShift,
        };
    }

    public function primaryDimensionLabel(): string
    {
        return match ($this->type) {
            ProductionReportType::Daily => 'Production date',
            ProductionReportType::Weekly => 'Week',
            ProductionReportType::Monthly,
            ProductionReportType::Executive => 'Month',
            ProductionReportType::ProductionLine => 'Production line',
            ProductionReportType::Product => 'Product',
            ProductionReportType::Shift => 'Shift',
        };
    }

    public function hasData(): bool
    {
        return ! $this->summary->isEmpty()
            || $this->primaryRows() !== [];
    }

    public function dataBasisLabel(): string
    {
        return 'This report reuses the validated deterministic production KPI and breakdown services. '
            .'Quantities with different units remain separated. '
            .'Pending in-progress values are identified as provisional, and rejected validation records are excluded.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'filter' => $this->filter->toArray(),
            'generated_at' =>
                $this->generatedAt->utc()->toIso8601String(),
            'generated_by' => [
                'name' => $this->generatedByName,
                'email' => $this->generatedByEmail,
            ],
            'applied_filters' => $this->appliedFilters,
            'data_basis' => $this->dataBasisLabel(),
            'has_data' => $this->hasData(),
            'summary' => $this->summary->toArray(),
            'primary_dimension' => $this->primaryDimensionLabel(),
            'primary_rows' => array_map(
                static fn (ProductionMetricRow $row): array =>
                    $row->toArray(),
                $this->primaryRows()
            ),
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
