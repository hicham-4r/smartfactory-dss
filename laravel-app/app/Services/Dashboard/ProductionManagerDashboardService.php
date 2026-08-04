<?php

namespace App\Services\Dashboard;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\DTOs\Dashboard\DashboardFilter;
use App\DTOs\Dashboard\DashboardFilterOption;
use App\DTOs\Dashboard\ProductionManagerDashboardSnapshot;
use App\DTOs\Dashboard\ProductionManagerEventItem;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionOrderStatus;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use App\Services\Analytics\ProductionBreakdownService;
use App\Services\Analytics\ProductionKpiService;
use App\Services\Analytics\QualityKpiService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ProductionManagerDashboardService
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
    ): ProductionManagerDashboardSnapshot {
        $analyticsFilter = $this->analyticsFilter($filter);

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

        $orderQuery = $this->eligibleOrderQuery($filter);
        $criticalEventQuery = $this->criticalEventQuery($filter);

        return new ProductionManagerDashboardSnapshot(
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
                    productionLineId: $filter->productionLineId,
                    productId: $filter->productId,
                    maximumRangeDays: (int) config(
                        'analytics.maximum_range_days',
                        366
                    ),
                )
            ),
            inProgressOrderCount: $this->statusCount(
                $orderQuery,
                $filter,
                ProductionOrderStatus::InProgress
            ),
            completedOrderCount: $this->statusCount(
                $orderQuery,
                $filter,
                ProductionOrderStatus::Completed
            ),
            criticalEventCount: (clone $criticalEventQuery)
                ->distinct()
                ->count('production_events.id'),
            criticalEvents: $this->recentCriticalEvents(
                $criticalEventQuery,
                $filter
            ),
            productionLines: $this->lineOptions(
                $this->analyticsRepository
                    ->filterableProductionLines($catalogueFilter)
            ),
            products: $this->productOptions(
                $this->analyticsRepository
                    ->filterableProducts($catalogueFilter)
            ),
            shifts: $this->shiftOptions(
                $this->analyticsRepository
                    ->filterableShifts($catalogueFilter)
            ),
            compatibilityRows: $this->analyticsRepository
                ->filterCompatibilityRows($catalogueFilter),
        );
    }

    private function analyticsFilter(
        DashboardFilter $filter
    ): AnalyticsFilter {
        return new AnalyticsFilter(
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            timezone: $filter->timezone,
            productionLineId: $filter->productionLineId,
            productId: $filter->productId,
            shiftId: $filter->shiftId,
            status: $filter->status,
            maximumRangeDays: (int) config(
                'analytics.maximum_range_days',
                366
            ),
        );
    }

    /** @return Builder<ProductionOrder> */
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
            $query->where('product_id', $filter->productId);
        }

        if ($filter->status !== null) {
            $query->where('status', $filter->status);
        }

        if ($filter->shiftId !== null) {
            $query->where(function (
                Builder $shiftScope
            ) use ($filter): void {
                $shiftScope
                    ->where('shift_id', $filter->shiftId)
                    ->orWhereHas(
                        'batches.records',
                        function (
                            Builder $records
                        ) use ($filter): void {
                            $records
                                ->where('shift_id', $filter->shiftId)
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

    /** @return Builder<ProductionEvent> */
    private function criticalEventQuery(
        DashboardFilter $filter
    ): Builder {
        $query = ProductionEvent::query()
            ->where('is_resolved', false)
            ->where(
                'severity',
                ProductionEventSeverity::Critical->value
            )
            ->where('started_at', '>=', $filter->utcStart())
            ->where('started_at', '<', $filter->utcEndExclusive());

        if ($filter->productionLineId !== null) {
            $query->where(
                'production_line_id',
                $filter->productionLineId
            );
        }

        if ($filter->shiftId !== null) {
            $query->where('shift_id', $filter->shiftId);
        }

        if ($filter->productId !== null) {
            $query->whereHas(
                'productionBatch.productionOrder',
                fn (Builder $order): Builder => $order->where(
                    'product_id',
                    $filter->productId
                )
            );
        }

        if ($filter->status !== null) {
            $query->whereHas(
                'productionBatch.productionOrder',
                fn (Builder $order): Builder => $order->where(
                    'status',
                    $filter->status
                )
            );
        }

        return $query;
    }

    /** @param Builder<ProductionOrder> $query */
    private function statusCount(
        Builder $query,
        DashboardFilter $filter,
        ProductionOrderStatus $status,
    ): int {
        if (
            $filter->status !== null
            && $filter->status !== $status->value
        ) {
            return 0;
        }

        return (clone $query)
            ->where('status', $status->value)
            ->distinct()
            ->count('production_orders.id');
    }

    /**
     * @param Builder<ProductionEvent> $query
     * @return list<ProductionManagerEventItem>
     */
    private function recentCriticalEvents(
        Builder $query,
        DashboardFilter $filter
    ): array {
        return (clone $query)
            ->with([
                'productionLine',
                'machine',
                'shift',
            ])
            ->orderByDesc('started_at')
            ->limit(8)
            ->get()
            ->map(
                fn (ProductionEvent $event): ProductionManagerEventItem =>
                    new ProductionManagerEventItem(
                        id: $event->getKey(),
                        eventNumber: (string) $event->event_number,
                        title: (string) $event->title,
                        eventType: $this->enumValue($event->event_type),
                        productionLineName: (string) (
                            $event->productionLine?->name
                            ?? 'Unknown line'
                        ),
                        machineName: $event->machine?->name,
                        shiftName: $event->shift?->name,
                        startedAt: $event->started_at
                            ?->setTimezone($filter->timezone)
                            ->format('Y-m-d H:i')
                            ?? 'N/A',
                        durationMinutes: $event->duration_minutes,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, object> $rows
     * @return list<DashboardFilterOption>
     */
    private function lineOptions(Collection $rows): array
    {
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
    private function productOptions(Collection $rows): array
    {
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
    private function shiftOptions(Collection $rows): array
    {
        return $rows
            ->map(
                static fn (object $row): DashboardFilterOption =>
                    new DashboardFilterOption(
                        id: (int) $row->id,
                        label: (string) $row->name,
                        filterValue: (string) (
                            $row->shift_key ?? $row->id
                        ),
                    )
            )
            ->values()
            ->all();
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
