<?php

namespace App\Services\Production;

use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Models\Product;
use App\Models\ProductionEvent;
use App\Models\ProductionLine;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\Shift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SupervisorProductionQueryService
{
    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'open_orders' =>
                ProductionOrder::query()
                    ->whereIn('status', [
                        ProductionOrderStatus::Draft->value,
                        ProductionOrderStatus::Planned->value,
                        ProductionOrderStatus::Released->value,
                        ProductionOrderStatus::InProgress->value,
                    ])
                    ->count(),

            'in_progress_orders' =>
                ProductionOrder::query()
                    ->where(
                        'status',
                        ProductionOrderStatus::InProgress->value
                    )
                    ->count(),

            'pending_validations' =>
                ProductionRecord::query()
                    ->where(
                        'status',
                        ProductionRecordStatus::Submitted->value
                    )
                    ->where(
                        'validation_status',
                        ProductionValidationStatus::Pending->value
                    )
                    ->count(),

            'critical_events' =>
                ProductionEvent::query()
                    ->where('is_resolved', false)
                    ->where(
                        'severity',
                        ProductionEventSeverity::Critical->value
                    )
                    ->count(),

            'unresolved_events' =>
                ProductionEvent::query()
                    ->where('is_resolved', false)
                    ->count(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function orders(
        array $filters,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ProductionOrder::query()
            ->with([
                'product.productFamily',
                'productionLine',
                'shift',
                'creator',
                'updater',
            ])
            ->withCount('batches');

        $search = $filters['search'] ?? null;

        if (
            is_string($search)
            && trim($search) !== ''
        ) {
            $pattern = $this->likePattern(
                $search
            );

            $query->where(
                function (
                    Builder $scope
                ) use ($pattern): void {
                    $scope
                        ->where(
                            'order_number',
                            'like',
                            $pattern
                        )
                        ->orWhereHas(
                            'product',
                            function (
                                Builder $productQuery
                            ) use ($pattern): void {
                                $productQuery
                                    ->where(
                                        'name',
                                        'like',
                                        $pattern
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        $pattern
                                    );
                            }
                        );
                }
            );
        }

        $status = $filters['status'] ?? null;

        if (
            is_string($status)
            && $status !== ''
        ) {
            $query->where(
                'status',
                $status
            );
        }

        if (
            ! empty(
                $filters['production_line_id']
            )
        ) {
            $query->where(
                'production_line_id',
                (int) $filters[
                    'production_line_id'
                ]
            );
        }

        if (
            ! empty(
                $filters['shift_id']
            )
        ) {
            $query->where(
                'shift_id',
                (int) $filters['shift_id']
            );
        }

        if (
            ! empty(
                $filters['date_from']
            )
        ) {
            $query->whereDate(
                'planned_start_at',
                '>=',
                $filters['date_from']
            );
        }

        if (
            ! empty(
                $filters['date_to']
            )
        ) {
            $query->whereDate(
                'planned_start_at',
                '<=',
                $filters['date_to']
            );
        }

        return $query
            ->orderByDesc(
                'planned_start_at'
            )
            ->orderByDesc('id')
            ->paginate(
                perPage: max(
                    1,
                    min($perPage, 100)
                ),
                pageName: 'orders_page'
            )
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function pendingRecords(
        array $filters,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ProductionRecord::query()
            ->where(
                'status',
                ProductionRecordStatus::Submitted->value
            )
            ->where(
                'validation_status',
                ProductionValidationStatus::Pending->value
            )
            ->with([
                'productionBatch.productionOrder.product',
                'productionLine',
                'shift',
                'operator',
                'recordedBy',
            ]);

        $search =
            $filters['record_search']
            ?? null;

        if (
            is_string($search)
            && trim($search) !== ''
        ) {
            $pattern = $this->likePattern(
                $search
            );

            $query->where(
                function (
                    Builder $scope
                ) use ($pattern): void {
                    $scope
                        ->where(
                            'record_number',
                            'like',
                            $pattern
                        )
                        ->orWhereHas(
                            'productionBatch.productionOrder',
                            fn (
                                Builder $orderQuery
                            ): Builder =>
                                $orderQuery->where(
                                    'order_number',
                                    'like',
                                    $pattern
                                )
                        );
                }
            );
        }

        if (
            ! empty(
                $filters['record_line_id']
            )
        ) {
            $query->where(
                'production_line_id',
                (int) $filters[
                    'record_line_id'
                ]
            );
        }

        return $query
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(
                perPage: max(
                    1,
                    min($perPage, 100)
                ),
                pageName: 'records_page'
            )
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function unresolvedEvents(
        array $filters,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ProductionEvent::query()
            ->where(
                'is_resolved',
                false
            )
            ->with([
                'productionBatch.productionOrder',
                'productionRecord',
                'productionLine',
                'machine',
                'shift',
                'operator',
                'reportedBy',
            ]);

        $search =
            $filters['event_search']
            ?? null;

        if (
            is_string($search)
            && trim($search) !== ''
        ) {
            $pattern = $this->likePattern(
                $search
            );

            $query->where(
                function (
                    Builder $scope
                ) use ($pattern): void {
                    $scope
                        ->where(
                            'event_number',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'title',
                            'like',
                            $pattern
                        );
                }
            );
        }

        $type =
            $filters['event_type']
            ?? null;

        if (
            is_string($type)
            && $type !== ''
        ) {
            $query->where(
                'event_type',
                $type
            );
        }

        $severity =
            $filters['event_severity']
            ?? null;

        if (
            is_string($severity)
            && $severity !== ''
        ) {
            $query->where(
                'severity',
                $severity
            );
        }

        if (
            ! empty(
                $filters['event_line_id']
            )
        ) {
            $query->where(
                'production_line_id',
                (int) $filters[
                    'event_line_id'
                ]
            );
        }

        return $query
            ->orderByRaw(
                "CASE severity
                    WHEN 'critical' THEN 1
                    WHEN 'warning' THEN 2
                    ELSE 3
                END"
            )
            ->orderBy('started_at')
            ->orderBy('id')
            ->paginate(
                perPage: max(
                    1,
                    min($perPage, 100)
                ),
                pageName: 'events_page'
            )
            ->withQueryString();
    }

    /**
     * @return Collection<int, Product>
     */
    public function activeProducts(): Collection
    {
        return Product::query()
            ->where(
                'is_active',
                true
            )
            ->with('productFamily')
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, ProductionLine>
     */
    public function activeProductionLines(): Collection
    {
        return ProductionLine::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Shift>
     */
    public function activeShifts(): Collection
    {
        return Shift::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return list<ProductionOrderStatus>
     */
    public function orderStatuses(): array
    {
        return ProductionOrderStatus::cases();
    }

    /**
     * @return list<ProductionEventType>
     */
    public function eventTypes(): array
    {
        return ProductionEventType::cases();
    }

    /**
     * @return list<ProductionEventSeverity>
     */
    public function eventSeverities(): array
    {
        return ProductionEventSeverity::cases();
    }

    private function likePattern(
        string $value
    ): string {
        return '%'
            .addcslashes(
                trim($value),
                '\\%_'
            )
            .'%';
    }
}