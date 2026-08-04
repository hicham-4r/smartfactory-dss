<?php

namespace App\DTOs\Dashboard;

use App\DTOs\Analytics\ProductionBreakdownReport;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\QualityKpiSummary;
use JsonSerializable;

final readonly class ProductionSupervisorDashboardSnapshot implements
    JsonSerializable
{
    /**
     * @param list<ProductionSupervisorOrderItem> $inProgressOrders
     * @param list<ProductionSupervisorRecordItem> $pendingRecords
     * @param list<ProductionSupervisorEventItem> $unresolvedEvents
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
        public int $pendingValidationCount,
        public int $unresolvedEventCount,
        public int $criticalEventCount,
        public array $inProgressOrders,
        public array $pendingRecords,
        public array $unresolvedEvents,
        public array $productionLines,
        public array $products,
        public array $shifts,
        public array $compatibilityRows,
    ) {
    }

    public function needsAttention(): bool
    {
        return $this->pendingValidationCount > 0
            || $this->unresolvedEventCount > 0
            || $this->quality->failedInspectionCount > 0
            || $this->quality->blockedLotCount > 0
            || $this->quality->rejectedLotCount > 0
            || $this->quality->criticalNonconformityCount > 0;
    }

    public function dataBasisLabel(): string
    {
        return 'Production quantities and trends reuse the verified analytics services. '
            .'Pending-validation and unresolved-event queues are read directly from the controlled production workflow tables for the selected period. '
            .'Quality attention counts use synchronized inspection, lot-release and nonconformity summaries. '
            .'Shift and execution-status filters do not alter quality counts because the synchronized quality schema does not preserve those dimensions.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'data_basis' => $this->dataBasisLabel(),
            'needs_attention' => $this->needsAttention(),
            'in_progress_order_count' => $this->inProgressOrderCount,
            'pending_validation_count' => $this->pendingValidationCount,
            'unresolved_event_count' => $this->unresolvedEventCount,
            'critical_event_count' => $this->criticalEventCount,
            'production' => $this->production->toArray(),
            'breakdowns' => $this->breakdowns->toArray(),
            'quality' => $this->quality->toArray(),
            'in_progress_orders' => array_map(
                static fn (
                    ProductionSupervisorOrderItem $item
                ): array => $item->toArray(),
                $this->inProgressOrders
            ),
            'pending_records' => array_map(
                static fn (
                    ProductionSupervisorRecordItem $item
                ): array => $item->toArray(),
                $this->pendingRecords
            ),
            'unresolved_events' => array_map(
                static fn (
                    ProductionSupervisorEventItem $item
                ): array => $item->toArray(),
                $this->unresolvedEvents
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
