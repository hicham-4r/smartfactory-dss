<?php

namespace App\Services\Dashboard;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\DTOs\Dashboard\DashboardFilter;
use App\DTOs\Dashboard\DashboardFilterOption;
use App\DTOs\Dashboard\ProductionSupervisorDashboardSnapshot;
use App\DTOs\Dashboard\ProductionSupervisorEventItem;
use App\DTOs\Dashboard\ProductionSupervisorOrderItem;
use App\DTOs\Dashboard\ProductionSupervisorRecordItem;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use App\Services\Analytics\ProductionBreakdownService;
use App\Services\Analytics\ProductionKpiService;
use App\Services\Analytics\QualityKpiService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ProductionSupervisorDashboardService
{
    public function __construct(
        private ProductionKpiService $productionKpis,
        private ProductionBreakdownService $productionBreakdowns,
        private QualityKpiService $qualityKpis,
        private ProductionAnalyticsRepositoryInterface $analyticsRepository,
    ) {
    }

    public function build(
        DashboardFilter $filter
    ): ProductionSupervisorDashboardSnapshot {
        $analyticsFilter = $this->analyticsFilter(
            $filter
        );

        $catalogueFilter = new AnalyticsFilter(
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            timezone: $filter->timezone,
            status: $filter->status,
            maximumRangeDays: (int) config(
                'analytics.maximum_range_days',
                366
            ),
        );

        $orderQuery = $this->eligibleOrderQuery(
            $filter
        );

        $recordQuery = $this->pendingRecordQuery(
            $filter
        );

        $eventQuery = $this->unresolvedEventQuery(
            $filter
        );

        $inProgressOrderCount =
            $filter->status !== null
            && $filter->status
                !== ProductionOrderStatus::InProgress->value
                ? 0
                : (clone $orderQuery)
                    ->where(
                        'status',
                        ProductionOrderStatus::InProgress->value
                    )
                    ->distinct()
                    ->count('production_orders.id');

        $pendingValidationCount =
            (clone $recordQuery)
                ->distinct()
                ->count('production_records.id');

        $unresolvedEventCount =
            (clone $eventQuery)
                ->distinct()
                ->count('production_events.id');

        $criticalEventCount =
            (clone $eventQuery)
                ->where(
                    'severity',
                    ProductionEventSeverity::Critical->value
                )
                ->distinct()
                ->count('production_events.id');

        return new ProductionSupervisorDashboardSnapshot(
            filter: $filter,
            production: $this->productionKpis
                ->summarize($analyticsFilter),
            breakdowns: $this->productionBreakdowns
                ->build($analyticsFilter),
            quality: $this->qualityKpis->summarize(
                new QualityAnalyticsFilter(
                    startDate: $filter->startDate,
                    endDate: $filter->endDate,
                    timezone: $filter->timezone,
                    productionLineId:
                        $filter->productionLineId,
                    productId: $filter->productId,
                    maximumRangeDays: (int) config(
                        'analytics.maximum_range_days',
                        366
                    ),
                )
            ),
            inProgressOrderCount:
                $inProgressOrderCount,
            pendingValidationCount:
                $pendingValidationCount,
            unresolvedEventCount:
                $unresolvedEventCount,
            criticalEventCount:
                $criticalEventCount,
            inProgressOrders:
                $this->recentInProgressOrders(
                    $orderQuery,
                    $filter
                ),
            pendingRecords:
                $this->recentPendingRecords(
                    $recordQuery
                ),
            unresolvedEvents:
                $this->recentUnresolvedEvents(
                    $eventQuery
                ),
            productionLines:
                $this->lineOptions(
                    $this->analyticsRepository
                        ->filterableProductionLines(
                            $catalogueFilter
                        )
                ),
            products:
                $this->productOptions(
                    $this->analyticsRepository
                        ->filterableProducts(
                            $catalogueFilter
                        )
                ),
            shifts:
                $this->shiftOptions(
                    $this->analyticsRepository
                        ->filterableShifts(
                            $catalogueFilter
                        )
                ),
            compatibilityRows:
                $this->analyticsRepository
                    ->filterCompatibilityRows(
                        $catalogueFilter
                    ),
        );
    }

    private function analyticsFilter(
        DashboardFilter $filter
    ): AnalyticsFilter {
        return new AnalyticsFilter(
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            timezone: $filter->timezone,
            productionLineId:
                $filter->productionLineId,
            productId: $filter->productId,
            shiftId: $filter->shiftId,
            status: $filter->status,
            maximumRangeDays: (int) config(
                'analytics.maximum_range_days',
                366
            ),
        );
    }

    /**
     * @return Builder<ProductionOrder>
     */
    private function eligibleOrderQuery(
        DashboardFilter $filter
    ): Builder {
        $query = ProductionOrder::query()
            ->where(function (
                Builder $eligible
            ) use ($filter): void {
                $eligible
                    ->where(function (
                        Builder $planned
                    ) use ($filter): void {
                        $planned
                            ->where(
                                'planned_start_at',
                                '>=',
                                $filter->utcStart()
                            )
                            ->where(
                                'planned_start_at',
                                '<',
                                $filter->utcEndExclusive()
                            );
                    })
                    ->orWhereHas(
                        'batches.records',
                        function (
                            Builder $records
                        ) use ($filter): void {
                            $records
                                ->whereDate(
                                    'production_date',
                                    '>=',
                                    $filter->startDateString()
                                )
                                ->whereDate(
                                    'production_date',
                                    '<=',
                                    $filter->endDateString()
                                );
                        }
                    );
            });

        if ($filter->productionLineId !== null) {
            $query->where(
                'production_line_id',
                $filter->productionLineId
            );
        }

        if ($filter->productId !== null) {
            $query->where(
                'product_id',
                $filter->productId
            );
        }

        if ($filter->status !== null) {
            $query->where(
                'status',
                $filter->status
            );
        }

        if ($filter->shiftId !== null) {
            $query->where(function (
                Builder $shiftScope
            ) use ($filter): void {
                $shiftScope
                    ->where(
                        'shift_id',
                        $filter->shiftId
                    )
                    ->orWhereHas(
                        'batches.records',
                        function (
                            Builder $records
                        ) use ($filter): void {
                            $records
                                ->where(
                                    'shift_id',
                                    $filter->shiftId
                                )
                                ->whereDate(
                                    'production_date',
                                    '>=',
                                    $filter->startDateString()
                                )
                                ->whereDate(
                                    'production_date',
                                    '<=',
                                    $filter->endDateString()
                                );
                        }
                    );
            });
        }

        return $query;
    }

    /**
     * @return Builder<ProductionRecord>
     */
    private function pendingRecordQuery(
        DashboardFilter $filter
    ): Builder {
        $query = ProductionRecord::query()
            ->where(
                'status',
                ProductionRecordStatus::Submitted->value
            )
            ->where(
                'validation_status',
                ProductionValidationStatus::Pending->value
            )
            ->whereDate(
                'production_date',
                '>=',
                $filter->startDateString()
            )
            ->whereDate(
                'production_date',
                '<=',
                $filter->endDateString()
            );

        if ($filter->productionLineId !== null) {
            $query->where(
                'production_line_id',
                $filter->productionLineId
            );
        }

        if ($filter->shiftId !== null) {
            $query->where(
                'shift_id',
                $filter->shiftId
            );
        }

        if ($filter->productId !== null) {
            $query->whereHas(
                'productionBatch.productionOrder',
                fn (Builder $order): Builder =>
                    $order->where(
                        'product_id',
                        $filter->productId
                    )
            );
        }

        if ($filter->status !== null) {
            $query->whereHas(
                'productionBatch.productionOrder',
                fn (Builder $order): Builder =>
                    $order->where(
                        'status',
                        $filter->status
                    )
            );
        }

        return $query;
    }

    /**
     * @return Builder<ProductionEvent>
     */
    private function unresolvedEventQuery(
        DashboardFilter $filter
    ): Builder {
        $query = ProductionEvent::query()
            ->where('is_resolved', false)
            ->where(
                'started_at',
                '>=',
                $filter->utcStart()
            )
            ->where(
                'started_at',
                '<',
                $filter->utcEndExclusive()
            );

        if ($filter->productionLineId !== null) {
            $query->where(
                'production_line_id',
                $filter->productionLineId
            );
        }

        if ($filter->shiftId !== null) {
            $query->where(
                'shift_id',
                $filter->shiftId
            );
        }

        if ($filter->productId !== null) {
            $query->whereHas(
                'productionBatch.productionOrder',
                fn (Builder $order): Builder =>
                    $order->where(
                        'product_id',
                        $filter->productId
                    )
            );
        }

        if ($filter->status !== null) {
            $query->whereHas(
                'productionBatch.productionOrder',
                fn (Builder $order): Builder =>
                    $order->where(
                        'status',
                        $filter->status
                    )
            );
        }

        return $query;
    }

    /**
     * @param Builder<ProductionOrder> $query
     *
     * @return list<ProductionSupervisorOrderItem>
     */
    private function recentInProgressOrders(
        Builder $query,
        DashboardFilter $filter
    ): array {
        if (
            $filter->status !== null
            && $filter->status
                !== ProductionOrderStatus::InProgress->value
        ) {
            return [];
        }

        return (clone $query)
            ->where(
                'status',
                ProductionOrderStatus::InProgress->value
            )
            ->with([
                'product',
                'productionLine',
                'shift',
            ])
            ->orderByDesc('priority')
            ->orderBy('planned_start_at')
            ->limit(8)
            ->get()
            ->map(
                fn (ProductionOrder $order): ProductionSupervisorOrderItem =>
                    new ProductionSupervisorOrderItem(
                        id: $order->getKey(),
                        orderNumber:
                            (string) $order->order_number,
                        productName:
                            (string) (
                                $order->product?->name
                                ?? 'Unknown product'
                            ),
                        productionLineName:
                            (string) (
                                $order->productionLine?->name
                                ?? 'Unknown line'
                            ),
                        shiftName:
                            $order->shift?->name,
                        status:
                            $this->enumValue(
                                $order->status
                            ),
                        plannedStartAt:
                            $order->planned_start_at
                                ?->setTimezone(
                                    $filter->timezone
                                )
                                ->format('Y-m-d H:i')
                            ?? 'N/A',
                        targetQuantity:
                            (string) $order->target_quantity,
                        quantityUnit:
                            (string) $order->quantity_unit,
                        priority:
                            (int) $order->priority,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Builder<ProductionRecord> $query
     *
     * @return list<ProductionSupervisorRecordItem>
     */
    private function recentPendingRecords(
        Builder $query
    ): array {
        return (clone $query)
            ->with([
                'productionBatch.productionOrder.product',
                'productionLine',
                'shift',
                'recordedBy',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(
                fn (ProductionRecord $record): ProductionSupervisorRecordItem =>
                    new ProductionSupervisorRecordItem(
                        id: $record->getKey(),
                        recordNumber:
                            (string) $record->record_number,
                        productName:
                            (string) (
                                $record
                                    ->productionBatch
                                    ?->productionOrder
                                    ?->product
                                    ?->name
                                ?? 'Unknown product'
                            ),
                        productionLineName:
                            (string) (
                                $record->productionLine?->name
                                ?? 'Unknown line'
                            ),
                        shiftName:
                            (string) (
                                $record->shift?->name
                                ?? 'Unknown shift'
                            ),
                        productionDate:
                            $record->production_date
                                ?->toDateString()
                            ?? 'N/A',
                        producedQuantity:
                            (string) $record->produced_quantity,
                        quantityUnit:
                            (string) $record->quantity_unit,
                        validationStatus:
                            $this->enumValue(
                                $record->validation_status
                            ),
                        recordedByName:
                            $record->recordedBy?->name,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Builder<ProductionEvent> $query
     *
     * @return list<ProductionSupervisorEventItem>
     */
    private function recentUnresolvedEvents(
        Builder $query
    ): array {
        return (clone $query)
            ->with([
                'productionLine',
                'machine',
                'shift',
            ])
            ->orderByRaw(
                "CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END"
            )
            ->orderByDesc('started_at')
            ->limit(8)
            ->get()
            ->map(
                fn (ProductionEvent $event): ProductionSupervisorEventItem =>
                    new ProductionSupervisorEventItem(
                        id: $event->getKey(),
                        eventNumber:
                            (string) $event->event_number,
                        title:
                            (string) $event->title,
                        eventType:
                            $this->enumValue(
                                $event->event_type
                            ),
                        severity:
                            $this->enumValue(
                                $event->severity
                            ),
                        productionLineName:
                            (string) (
                                $event->productionLine?->name
                                ?? 'Unknown line'
                            ),
                        machineName:
                            $event->machine?->name,
                        shiftName:
                            $event->shift?->name,
                        startedAt:
                            $event->started_at
                                ?->toIso8601String()
                            ?? 'N/A',
                        durationMinutes:
                            $event->duration_minutes,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, object> $rows
     * @return list<DashboardFilterOption>
     */
    private function lineOptions(
        Collection $rows
    ): array {
        return $rows
            ->map(
                static fn (object $row): DashboardFilterOption =>
                    new DashboardFilterOption(
                        id: (int) $row->id,
                        label: (string) $row->name,
                        filterValue: (string) $row->id,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, object> $rows
     * @return list<DashboardFilterOption>
     */
    private function productOptions(
        Collection $rows
    ): array {
        return $rows
            ->map(
                static fn (object $row): DashboardFilterOption =>
                    new DashboardFilterOption(
                        id: (int) $row->id,
                        label: (string) $row->name,
                        filterValue: (string) $row->id,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, object> $rows
     * @return list<DashboardFilterOption>
     */
    private function shiftOptions(
        Collection $rows
    ): array {
        return $rows
            ->map(
                static fn (object $row): DashboardFilterOption =>
                    new DashboardFilterOption(
                        id: (int) $row->id,
                        label: (string) $row->name,
                        filterValue: (string) (
                            $row->shift_key
                            ?? $row->id
                        ),
                    )
            )
            ->values()
            ->all();
    }

    private function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
