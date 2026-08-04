<?php

namespace App\DTOs\Dashboard;

use App\DTOs\Analytics\ProductionBreakdownReport;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\QualityKpiSummary;
use JsonSerializable;

final readonly class ProductionManagerDashboardSnapshot implements
    JsonSerializable
{
    /**
     * @param list<ProductionManagerEventItem> $criticalEvents
     * @param list<DashboardFilterOption> $productionLines
     * @param list<DashboardFilterOption> $products
     * @param list<DashboardFilterOption> $shifts
     * @param list<array{
     *     production_line_id:int,
     *     product_family_id:int,
     *     product_id:int,
     *     shift_key:?string,
     *     status:string
     * }> $compatibilityRows
     */
    public function __construct(
        public DashboardFilter $filter,
        public ProductionKpiSummary $production,
        public ProductionBreakdownReport $breakdowns,
        public QualityKpiSummary $quality,
        public int $inProgressOrderCount,
        public int $completedOrderCount,
        public int $criticalEventCount,
        public array $criticalEvents,
        public array $productionLines,
        public array $products,
        public array $shifts,
        public array $compatibilityRows,
    ) {
    }

    public function needsAttention(): bool
    {
        return $this->criticalEventCount > 0
            || $this->quality->failedInspectionCount > 0
            || $this->quality->blockedLotCount > 0
            || $this->quality->rejectedLotCount > 0
            || $this->quality->criticalNonconformityCount > 0;
    }

    public function dataBasisLabel(): string
    {
        return 'Executive production quantities, trends and rankings reuse the verified analytics services. '
            .'Critical-event counts use unresolved critical production events in the selected period. '
            .'Quality risk indicators reuse synchronized inspection, finished-lot and nonconformity summaries. '
            .'Quantities with different units remain separated, and no forecast or AI conclusion is generated in this deterministic step.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'data_basis' => $this->dataBasisLabel(),
            'needs_attention' => $this->needsAttention(),
            'in_progress_order_count' => $this->inProgressOrderCount,
            'completed_order_count' => $this->completedOrderCount,
            'critical_event_count' => $this->criticalEventCount,
            'production' => $this->production->toArray(),
            'breakdowns' => $this->breakdowns->toArray(),
            'quality' => $this->quality->toArray(),
            'critical_events' => array_map(
                static fn (
                    ProductionManagerEventItem $item
                ): array => $item->toArray(),
                $this->criticalEvents
            ),
            'production_lines' => array_map(
                static fn (
                    DashboardFilterOption $option
                ): array => $option->toArray(),
                $this->productionLines
            ),
            'products' => array_map(
                static fn (
                    DashboardFilterOption $option
                ): array => $option->toArray(),
                $this->products
            ),
            'shifts' => array_map(
                static fn (
                    DashboardFilterOption $option
                ): array => $option->toArray(),
                $this->shifts
            ),
            'compatibility_rows' => $this->compatibilityRows,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
