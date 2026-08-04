<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Analytics\AnalyticsFilter;
use App\Enums\Analytics\ProductionBreakdownDimension;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentProductionAnalyticsRepository implements
    ProductionAnalyticsRepositoryInterface
{
    public function validatedProductionByUnit(
        AnalyticsFilter $filter
    ): array {
        return $this->productionAggregation(
            filter: $filter,
            includePending: false
        );
    }

    public function productionByUnit(
        AnalyticsFilter $filter
    ): array {
        return $this->productionAggregation(
            filter: $filter,
            includePending:
                $filter->status
                !== ProductionOrderStatus::Completed->value
        );
    }

    public function scheduledTargetsByUnit(
        AnalyticsFilter $filter
    ): array {
        if ($filter->shiftId === null) {
            $query = DB::table('production_orders as po')
                ->join(
                    'products as p',
                    'p.id',
                    '=',
                    'po.product_id'
                )
                ->whereIn(
                    'po.import_status',
                    $this->includedImportStatuses()
                )
                ->where(
                    'po.planned_start_at',
                    '>=',
                    $filter->utcStart()->toDateTimeString()
                )
                ->where(
                    'po.planned_start_at',
                    '<',
                    $filter->utcEndExclusive()->toDateTimeString()
                );

            $this->applyOrderFilters(
                query: $query,
                filter: $filter
            );

            return $query
                ->groupBy('po.quantity_unit')
                ->orderBy('po.quantity_unit')
                ->selectRaw(
                    'po.quantity_unit as quantity_unit'
                )
                ->selectRaw(
                    'COUNT(DISTINCT po.id) as target_order_count'
                )
                ->selectRaw(
                    'COALESCE(SUM(po.target_quantity), 0) as target_quantity'
                )
                ->get()
                ->all();
        }

        /*
         * The simulator exposes execution shifts on production records, while
         * the synchronized production-order shift may be null. For a
         * shift-specific denominator, aggregate each matching batch once using
         * production_batches.planned_quantity and the record shift assignment.
         */
        $shiftIds = $this->equivalentShiftIds(
            $filter->shiftId
        );

        $query = DB::table('production_batches as pb')
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->whereIn(
                'po.import_status',
                $this->includedImportStatuses()
            )
            ->whereIn(
                'pb.import_status',
                $this->includedImportStatuses()
            )
            ->whereExists(
                function (Builder $records) use (
                    $filter,
                    $shiftIds
                ): void {
                    $records
                        ->selectRaw('1')
                        ->from(
                            'production_records as target_pr'
                        )
                        ->whereColumn(
                            'target_pr.production_batch_id',
                            'pb.id'
                        )
                        ->whereIn(
                            'target_pr.shift_id',
                            $shiftIds
                        )
                        ->whereDate(
                            'target_pr.production_date',
                            '>=',
                            $filter->startDateString()
                        )
                        ->whereDate(
                            'target_pr.production_date',
                            '<=',
                            $filter->endDateString()
                        )
                        ->whereIn(
                            'target_pr.import_status',
                            $this->includedImportStatuses()
                        )
                        ->where(function (Builder $eligibleRecord) use (
                            $filter
                        ): void {
                            if (
                                $filter->status
                                === ProductionOrderStatus::Completed->value
                            ) {
                                $eligibleRecord->where(
                                    'target_pr.validation_status',
                                    ProductionValidationStatus::Validated->value
                                );

                                return;
                            }

                            if (
                                $filter->status
                                === ProductionOrderStatus::InProgress->value
                            ) {
                                $eligibleRecord->whereIn(
                                    'target_pr.validation_status',
                                    [
                                        ProductionValidationStatus::Validated->value,
                                        ProductionValidationStatus::Pending->value,
                                    ]
                                );

                                return;
                            }

                            $eligibleRecord
                                ->where(function (Builder $completed): void {
                                    $completed
                                        ->where(
                                            'po.status',
                                            ProductionOrderStatus::Completed->value
                                        )
                                        ->where(
                                            'target_pr.validation_status',
                                            ProductionValidationStatus::Validated->value
                                        );
                                })
                                ->orWhere(function (Builder $inProgress): void {
                                    $inProgress
                                        ->where(
                                            'po.status',
                                            ProductionOrderStatus::InProgress->value
                                        )
                                        ->whereIn(
                                            'target_pr.validation_status',
                                            [
                                                ProductionValidationStatus::Validated->value,
                                                ProductionValidationStatus::Pending->value,
                                            ]
                                        );
                                });
                        });
                }
            );

        $this->applyOrderFilters(
            query: $query,
            filter: $filter,
            includeShift: false
        );

        return $query
            ->groupBy('pb.quantity_unit')
            ->orderBy('pb.quantity_unit')
            ->selectRaw(
                'pb.quantity_unit as quantity_unit'
            )
            ->selectRaw(
                'COUNT(DISTINCT po.id) as target_order_count'
            )
            ->selectRaw(
                'COALESCE(SUM(pb.planned_quantity), 0) as target_quantity'
            )
            ->get()
            ->all();
    }


    public function dailyProductionMetrics(
        AnalyticsFilter $filter
    ): array {
        $query = $this->eligibleProductionRecordsQuery(
            $filter
        );

        return $query
            ->groupBy(
                'pr.production_date',
                'pr.quantity_unit'
            )
            ->orderBy('pr.production_date')
            ->orderBy('pr.quantity_unit')
            ->selectRaw(
                'pr.production_date as metric_key'
            )
            ->selectRaw(
                'pr.quantity_unit as quantity_unit'
            )
            ->selectRaw(
                'COUNT(pr.id) as record_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN pr.validation_status = ? THEN 1 ELSE 0 END) as validated_record_count',
                [ProductionValidationStatus::Validated->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN pr.validation_status = ? THEN 1 ELSE 0 END) as provisional_record_count',
                [ProductionValidationStatus::Pending->value]
            )
            ->selectRaw(
                'COALESCE(SUM(pr.produced_quantity), 0) as actual_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.good_quantity), 0) as good_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.rejected_quantity), 0) as rejected_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.runtime_minutes), 0) as runtime_minutes'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.downtime_minutes), 0) as downtime_minutes'
            )
            ->get()
            ->all();
    }

    public function dailyScheduledTargets(
        AnalyticsFilter $filter
    ): array {
        if ($filter->shiftId === null) {
            $query = DB::table('production_orders as po')
                ->join(
                    'products as p',
                    'p.id',
                    '=',
                    'po.product_id'
                )
                ->whereIn(
                    'po.import_status',
                    $this->includedImportStatuses()
                )
                ->where(
                    'po.planned_start_at',
                    '>=',
                    $filter->utcStart()
                        ->toDateTimeString()
                )
                ->where(
                    'po.planned_start_at',
                    '<',
                    $filter->utcEndExclusive()
                        ->toDateTimeString()
                );

            $this->applyOrderFilters(
                query: $query,
                filter: $filter
            );

            return $query
                ->groupBy(
                    DB::raw(
                        'DATE(po.planned_start_at)'
                    ),
                    'po.quantity_unit'
                )
                ->orderBy(
                    DB::raw(
                        'DATE(po.planned_start_at)'
                    )
                )
                ->orderBy('po.quantity_unit')
                ->selectRaw(
                    'DATE(po.planned_start_at) as metric_key'
                )
                ->selectRaw(
                    'po.quantity_unit as quantity_unit'
                )
                ->selectRaw(
                    'COUNT(DISTINCT po.id) as target_count'
                )
                ->selectRaw(
                    'COALESCE(SUM(po.target_quantity), 0) as target_quantity'
                )
                ->get()
                ->all();
        }

        $batchTargets = $this->batchTargetRows(
            filter: $filter,
            splitByShift: false
        );

        return DB::query()
            ->fromSub(
                $batchTargets,
                'batch_targets'
            )
            ->groupBy(
                'metric_key',
                'quantity_unit'
            )
            ->orderBy('metric_key')
            ->orderBy('quantity_unit')
            ->select([
                'metric_key',
                'quantity_unit',
            ])
            ->selectRaw(
                'COUNT(DISTINCT order_id) as target_count'
            )
            ->selectRaw(
                'COALESCE(SUM(planned_quantity), 0) as target_quantity'
            )
            ->get()
            ->all();
    }

    public function productionBreakdown(
        AnalyticsFilter $filter,
        ProductionBreakdownDimension $dimension
    ): array {
        $query = $this->eligibleProductionRecordsQuery(
            $filter
        );

        [
            $dimensionId,
            $dimensionLabel,
        ] = match ($dimension) {
            ProductionBreakdownDimension::ProductionLine => [
                'dimension_pl.id',
                'dimension_pl.name',
            ],

            ProductionBreakdownDimension::Shift => [
                'dimension_s.id',
                'dimension_s.name',
            ],

            ProductionBreakdownDimension::Product => [
                'p.id',
                'p.name',
            ],

            ProductionBreakdownDimension::ProductFamily => [
                'dimension_pf.id',
                'dimension_pf.name',
            ],
        };

        match ($dimension) {
            ProductionBreakdownDimension::ProductionLine =>
                $query->join(
                    'production_lines as dimension_pl',
                    'dimension_pl.id',
                    '=',
                    DB::raw(
                        'COALESCE(pr.production_line_id, po.production_line_id)'
                    )
                ),

            ProductionBreakdownDimension::Shift =>
                $query->join(
                    'shifts as dimension_s',
                    'dimension_s.id',
                    '=',
                    DB::raw(
                        'COALESCE(pr.shift_id, po.shift_id)'
                    )
                ),

            ProductionBreakdownDimension::Product =>
                null,

            ProductionBreakdownDimension::ProductFamily =>
                $query->join(
                    'product_families as dimension_pf',
                    'dimension_pf.id',
                    '=',
                    'p.product_family_id'
                ),
        };

        return $query
            ->groupBy(
                $dimensionId,
                $dimensionLabel,
                'pr.quantity_unit'
            )
            ->orderBy($dimensionLabel)
            ->orderBy('pr.quantity_unit')
            ->selectRaw(
                "{$dimensionId} as dimension_key"
            )
            ->selectRaw(
                "{$dimensionLabel} as dimension_label"
            )
            ->selectRaw(
                'pr.quantity_unit as quantity_unit'
            )
            ->selectRaw(
                'COUNT(pr.id) as record_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN pr.validation_status = ? THEN 1 ELSE 0 END) as validated_record_count',
                [ProductionValidationStatus::Validated->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN pr.validation_status = ? THEN 1 ELSE 0 END) as provisional_record_count',
                [ProductionValidationStatus::Pending->value]
            )
            ->selectRaw(
                'COALESCE(SUM(pr.produced_quantity), 0) as actual_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.good_quantity), 0) as good_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.rejected_quantity), 0) as rejected_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.runtime_minutes), 0) as runtime_minutes'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.downtime_minutes), 0) as downtime_minutes'
            )
            ->get()
            ->all();
    }

    public function scheduledTargetBreakdown(
        AnalyticsFilter $filter,
        ProductionBreakdownDimension $dimension
    ): array {
        if (
            $dimension
            === ProductionBreakdownDimension::Shift
        ) {
            $batchTargets = $this->batchTargetRows(
                filter: $filter,
                splitByShift: true
            );

            return DB::query()
                ->fromSub(
                    $batchTargets,
                    'batch_targets'
                )
                ->whereNotNull('shift_id')
                ->groupBy(
                    'shift_id',
                    'shift_name',
                    'quantity_unit'
                )
                ->orderBy('shift_name')
                ->orderBy('quantity_unit')
                ->selectRaw(
                    'shift_id as dimension_key'
                )
                ->selectRaw(
                    'shift_name as dimension_label'
                )
                ->selectRaw(
                    'quantity_unit as quantity_unit'
                )
                ->selectRaw(
                    'COUNT(DISTINCT order_id) as target_count'
                )
                ->selectRaw(
                    'COALESCE(SUM(planned_quantity), 0) as target_quantity'
                )
                ->get()
                ->all();
        }

        if ($filter->shiftId !== null) {
            $batchTargets = $this->batchTargetRows(
                filter: $filter,
                splitByShift: false
            );

            [
                $dimensionId,
                $dimensionLabel,
            ] = match ($dimension) {
                ProductionBreakdownDimension::ProductionLine => [
                    'production_line_id',
                    'line_name',
                ],

                ProductionBreakdownDimension::Product => [
                    'product_id',
                    'product_name',
                ],

                ProductionBreakdownDimension::ProductFamily => [
                    'product_family_id',
                    'family_name',
                ],

                ProductionBreakdownDimension::Shift => [
                    'shift_id',
                    'shift_name',
                ],
            };

            return DB::query()
                ->fromSub(
                    $batchTargets,
                    'batch_targets'
                )
                ->groupBy(
                    $dimensionId,
                    $dimensionLabel,
                    'quantity_unit'
                )
                ->orderBy($dimensionLabel)
                ->orderBy('quantity_unit')
                ->selectRaw(
                    "{$dimensionId} as dimension_key"
                )
                ->selectRaw(
                    "{$dimensionLabel} as dimension_label"
                )
                ->selectRaw(
                    'quantity_unit as quantity_unit'
                )
                ->selectRaw(
                    'COUNT(DISTINCT order_id) as target_count'
                )
                ->selectRaw(
                    'COALESCE(SUM(planned_quantity), 0) as target_quantity'
                )
                ->get()
                ->all();
        }

        $query = DB::table('production_orders as po')
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->whereIn(
                'po.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'po.planned_start_at',
                '>=',
                $filter->utcStart()
                    ->toDateTimeString()
            )
            ->where(
                'po.planned_start_at',
                '<',
                $filter->utcEndExclusive()
                    ->toDateTimeString()
            );

        [
            $dimensionId,
            $dimensionLabel,
        ] = match ($dimension) {
            ProductionBreakdownDimension::ProductionLine => [
                'dimension_pl.id',
                'dimension_pl.name',
            ],

            ProductionBreakdownDimension::Product => [
                'p.id',
                'p.name',
            ],

            ProductionBreakdownDimension::ProductFamily => [
                'dimension_pf.id',
                'dimension_pf.name',
            ],

            ProductionBreakdownDimension::Shift => [
                'po.shift_id',
                'dimension_s.name',
            ],
        };

        match ($dimension) {
            ProductionBreakdownDimension::ProductionLine =>
                $query->join(
                    'production_lines as dimension_pl',
                    'dimension_pl.id',
                    '=',
                    'po.production_line_id'
                ),

            ProductionBreakdownDimension::Product =>
                null,

            ProductionBreakdownDimension::ProductFamily =>
                $query->join(
                    'product_families as dimension_pf',
                    'dimension_pf.id',
                    '=',
                    'p.product_family_id'
                ),

            ProductionBreakdownDimension::Shift =>
                $query->leftJoin(
                    'shifts as dimension_s',
                    'dimension_s.id',
                    '=',
                    'po.shift_id'
                ),
        };

        $this->applyOrderFilters(
            query: $query,
            filter: $filter
        );

        return $query
            ->groupBy(
                $dimensionId,
                $dimensionLabel,
                'po.quantity_unit'
            )
            ->orderBy($dimensionLabel)
            ->orderBy('po.quantity_unit')
            ->selectRaw(
                "{$dimensionId} as dimension_key"
            )
            ->selectRaw(
                "{$dimensionLabel} as dimension_label"
            )
            ->selectRaw(
                'po.quantity_unit as quantity_unit'
            )
            ->selectRaw(
                'COUNT(DISTINCT po.id) as target_count'
            )
            ->selectRaw(
                'COALESCE(SUM(po.target_quantity), 0) as target_quantity'
            )
            ->get()
            ->all();
    }

    public function filterableProductionOrders(
        AnalyticsFilter $filter,
        int $limit = 250
    ): Collection {
        $query = DB::table('production_orders as po')
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->leftJoin(
                'production_batches as order_pb',
                'order_pb.production_order_id',
                '=',
                'po.id'
            )
            ->leftJoin(
                'production_records as order_pr',
                function (JoinClause $join) use (
                    $filter
                ): void {
                    $join
                        ->on(
                            'order_pr.production_batch_id',
                            '=',
                            'order_pb.id'
                        )
                        ->whereDate(
                            'order_pr.production_date',
                            '>=',
                            $filter->startDateString()
                        )
                        ->whereDate(
                            'order_pr.production_date',
                            '<=',
                            $filter->endDateString()
                        );
                }
            )
            ->whereIn(
                'po.import_status',
                $this->includedImportStatuses()
            )
            ->where(function (Builder $eligible) use (
                $filter
            ): void {
                $eligible
                    ->where(function (Builder $scheduled) use (
                        $filter
                    ): void {
                        $scheduled
                            ->where(
                                'po.planned_start_at',
                                '>=',
                                $filter->utcStart()
                                    ->toDateTimeString()
                            )
                            ->where(
                                'po.planned_start_at',
                                '<',
                                $filter->utcEndExclusive()
                                    ->toDateTimeString()
                            );
                    })
                    ->orWhereNotNull('order_pr.id');
            });

        $this->applyOrderFilters(
            query: $query,
            filter: $filter,
            includeOrderId: false,
            recordAlias: 'order_pr'
        );

        $orders = $query
            ->select([
                'po.id',
                'po.order_number',
                'po.quantity_unit',
                'po.planned_start_at',
                'po.status',
            ])
            ->distinct()
            ->orderByDesc('po.planned_start_at')
            ->orderByDesc('po.id')
            ->limit(max(1, min($limit, 500)))
            ->get();

        if (
            $filter->productionOrderId !== null
            && ! $orders->contains(
                'id',
                $filter->productionOrderId
            )
        ) {
            $selectedOrder = DB::table(
                'production_orders'
            )
                ->where(
                    'id',
                    $filter->productionOrderId
                )
                ->first([
                    'id',
                    'order_number',
                    'quantity_unit',
                    'planned_start_at',
                    'status',
                ]);

            if ($selectedOrder !== null) {
                $orders->prepend($selectedOrder);
            }
        }

        return $orders;
    }

    public function filterableProductFamilies(
        AnalyticsFilter $filter
    ): Collection {
        $rows = $this->catalogueQuery($filter)
            ->join(
                'product_families as pf',
                'pf.id',
                '=',
                'p.product_family_id'
            )
            ->where('pf.is_active', true)
            ->groupBy('pf.id', 'pf.name')
            ->select([
                'pf.id',
                'pf.name',
            ])
            ->selectRaw(
                'COUNT(DISTINCT po.id) as order_count'
            )
            ->get();

        return $this->canonicalizeOptions(
            rows: $rows,
            nameProperty: 'name'
        );
    }

    public function filterableProducts(
        AnalyticsFilter $filter
    ): Collection {
        $rows = $this->catalogueQuery($filter)
            ->where('p.is_active', true)
            ->groupBy(
                'p.id',
                'p.name',
                'p.product_family_id'
            )
            ->select([
                'p.id',
                'p.name',
                'p.product_family_id',
            ])
            ->selectRaw(
                'COUNT(DISTINCT po.id) as order_count'
            )
            ->get();

        $familyOptions = $this
            ->filterableProductFamilies($filter)
            ->keyBy(
                fn (object $family): string =>
                    $this->normalizedName($family->name)
            );

        $familyNames = DB::table('product_families')
            ->pluck('name', 'id');

        $canonical = $this->canonicalizeOptions(
            rows: $rows,
            nameProperty: 'name'
        );

        return $canonical
            ->map(function (object $product) use (
                $familyOptions,
                $familyNames
            ): object {
                $familyName = $familyNames->get(
                    $product->product_family_id
                );

                $familyKey = $this->normalizedName(
                    is_string($familyName)
                        ? $familyName
                        : ''
                );

                $canonicalFamily = $familyOptions
                    ->get($familyKey);

                if ($canonicalFamily !== null) {
                    $product->product_family_id =
                        (int) $canonicalFamily->id;
                }

                return $product;
            })
            ->values();
    }

    public function filterableProductionLines(
        AnalyticsFilter $filter
    ): Collection {
        $rows = $this->catalogueQuery($filter)
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'po.production_line_id'
            )
            ->where('pl.is_active', true)
            ->groupBy('pl.id', 'pl.name')
            ->select([
                'pl.id',
                'pl.name',
            ])
            ->selectRaw(
                'COUNT(DISTINCT po.id) as order_count'
            )
            ->get();

        return $this->canonicalizeOptions(
            rows: $rows,
            nameProperty: 'name'
        );
    }

    public function filterableShifts(
        AnalyticsFilter $filter
    ): Collection {
        $rows = $this->catalogueQuery($filter)
            ->leftJoin(
                'production_batches as shift_pb',
                'shift_pb.production_order_id',
                '=',
                'po.id'
            )
            ->leftJoin(
                'production_records as shift_pr',
                function (JoinClause $join) use (
                    $filter
                ): void {
                    $join
                        ->on(
                            'shift_pr.production_batch_id',
                            '=',
                            'shift_pb.id'
                        )
                        ->whereDate(
                            'shift_pr.production_date',
                            '>=',
                            $filter->startDateString()
                        )
                        ->whereDate(
                            'shift_pr.production_date',
                            '<=',
                            $filter->endDateString()
                        );
                }
            )
            ->join(
                'shifts as s',
                's.id',
                '=',
                DB::raw(
                    'COALESCE(shift_pr.shift_id, po.shift_id)'
                )
            )
            ->where('s.is_active', true)
            ->groupBy(
                's.id',
                's.name',
                's.starts_at',
                's.ends_at'
            )
            ->select([
                's.id',
                's.name',
                's.starts_at',
                's.ends_at',
            ])
            ->selectRaw(
                'COUNT(DISTINCT po.id) as order_count'
            )
            ->get();

        return $rows
            ->groupBy(
                fn (object $row): string =>
                    implode('|', [
                        $this->normalizedName($row->name),
                        (string) $row->starts_at,
                        (string) $row->ends_at,
                    ])
            )
            ->map(
                fn (Collection $duplicates): object =>
                    $this->preferredOption($duplicates)
            )
            ->sortBy([
                ['starts_at', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(function (object $shift): object {
                $shift->shift_key = $this->normalizedName(
                    $shift->name
                );

                return $shift;
            });
    }

    public function filterCompatibilityRows(
        AnalyticsFilter $filter,
        int $limit = 5000
    ): array {
        $productOptions = $this
            ->filterableProducts($filter)
            ->keyBy(
                fn (object $item): string =>
                    $this->normalizedName($item->name)
            );

        $familyOptions = $this
            ->filterableProductFamilies($filter)
            ->keyBy(
                fn (object $item): string =>
                    $this->normalizedName($item->name)
            );

        $lineOptions = $this
            ->filterableProductionLines($filter)
            ->keyBy(
                fn (object $item): string =>
                    $this->normalizedName($item->name)
            );

        $rows = $this->catalogueQuery($filter)
            ->join(
                'product_families as pf',
                'pf.id',
                '=',
                'p.product_family_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'po.production_line_id'
            )
            ->leftJoin(
                'production_batches as pb',
                'pb.production_order_id',
                '=',
                'po.id'
            )
            ->leftJoin(
                'production_records as pr',
                function (JoinClause $join) use (
                    $filter
                ): void {
                    $join
                        ->on(
                            'pr.production_batch_id',
                            '=',
                            'pb.id'
                        )
                        ->whereDate(
                            'pr.production_date',
                            '>=',
                            $filter->startDateString()
                        )
                        ->whereDate(
                            'pr.production_date',
                            '<=',
                            $filter->endDateString()
                        );
                }
            )
            ->leftJoin(
                'shifts as s',
                's.id',
                '=',
                DB::raw(
                    'COALESCE(pr.shift_id, po.shift_id)'
                )
            )
            ->select([
                'p.name as product_name',
                'pf.name as family_name',
                'pl.name as line_name',
                's.name as shift_name',
                'po.status',
            ])
            ->distinct()
            ->limit(max(1, min($limit, 20000)))
            ->get();

        return $rows
            ->map(function (object $row) use (
                $productOptions,
                $familyOptions,
                $lineOptions
            ): ?array {
                $product = $productOptions->get(
                    $this->normalizedName(
                        $row->product_name
                    )
                );

                $family = $familyOptions->get(
                    $this->normalizedName(
                        $row->family_name
                    )
                );

                $line = $lineOptions->get(
                    $this->normalizedName(
                        $row->line_name
                    )
                );

                if (
                    $product === null
                    || $family === null
                    || $line === null
                ) {
                    return null;
                }

                return [
                    'production_line_id' =>
                        (int) $line->id,
                    'product_family_id' =>
                        (int) $family->id,
                    'product_id' =>
                        (int) $product->id,
                    'shift_key' =>
                        $row->shift_name === null
                            ? null
                            : $this->normalizedName(
                                $row->shift_name
                            ),
                    'status' => (string) $row->status,
                ];
            })
            ->filter()
            ->unique(
                static fn (array $row): string =>
                    implode('|', [
                        $row['production_line_id'],
                        $row['product_family_id'],
                        $row['product_id'],
                        $row['shift_key'] ?? '',
                        $row['status'],
                    ])
            )
            ->values()
            ->all();
    }


    private function eligibleProductionRecordsQuery(
        AnalyticsFilter $filter
    ): Builder {
        $query = DB::table('production_records as pr')
            ->join(
                'production_batches as pb',
                'pb.id',
                '=',
                'pr.production_batch_id'
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->whereIn(
                'pr.import_status',
                $this->includedImportStatuses()
            )
            ->whereIn(
                'pb.import_status',
                $this->includedImportStatuses()
            )
            ->whereIn(
                'po.import_status',
                $this->includedImportStatuses()
            )
            ->whereDate(
                'pr.production_date',
                '>=',
                $filter->startDateString()
            )
            ->whereDate(
                'pr.production_date',
                '<=',
                $filter->endDateString()
            );

        $this->applyRecordEligibility(
            query: $query,
            filter: $filter,
            recordAlias: 'pr',
            orderAlias: 'po'
        );

        $this->applyOrderFilters(
            query: $query,
            filter: $filter,
            recordAlias: 'pr'
        );

        return $query;
    }

    private function applyRecordEligibility(
        Builder $query,
        AnalyticsFilter $filter,
        string $recordAlias,
        string $orderAlias
    ): void {
        if (
            $filter->status
            === ProductionOrderStatus::Completed->value
        ) {
            $query->where(
                "{$recordAlias}.validation_status",
                ProductionValidationStatus::Validated->value
            );

            return;
        }

        if (
            $filter->status
            === ProductionOrderStatus::InProgress->value
        ) {
            $query->whereIn(
                "{$recordAlias}.validation_status",
                [
                    ProductionValidationStatus::Validated->value,
                    ProductionValidationStatus::Pending->value,
                ]
            );

            return;
        }

        $query->where(
            function (Builder $eligible) use (
                $recordAlias,
                $orderAlias
            ): void {
                $eligible
                    ->where(
                        "{$recordAlias}.validation_status",
                        ProductionValidationStatus::Validated->value
                    )
                    ->orWhere(
                        function (Builder $provisional) use (
                            $recordAlias,
                            $orderAlias
                        ): void {
                            $provisional
                                ->where(
                                    "{$recordAlias}.validation_status",
                                    ProductionValidationStatus::Pending->value
                                )
                                ->where(
                                    "{$orderAlias}.status",
                                    ProductionOrderStatus::InProgress->value
                                );
                        }
                    );
            }
        );
    }

    private function batchTargetRows(
        AnalyticsFilter $filter,
        bool $splitByShift
    ): Builder {
        $query = DB::table('production_records as target_pr')
            ->join(
                'production_batches as pb',
                'pb.id',
                '=',
                'target_pr.production_batch_id'
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->join(
                'product_families as pf',
                'pf.id',
                '=',
                'p.product_family_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'po.production_line_id'
            )
            ->leftJoin(
                'shifts as s',
                's.id',
                '=',
                DB::raw(
                    'COALESCE(target_pr.shift_id, po.shift_id)'
                )
            )
            ->whereIn(
                'target_pr.import_status',
                $this->includedImportStatuses()
            )
            ->whereIn(
                'pb.import_status',
                $this->includedImportStatuses()
            )
            ->whereIn(
                'po.import_status',
                $this->includedImportStatuses()
            )
            ->whereDate(
                'target_pr.production_date',
                '>=',
                $filter->startDateString()
            )
            ->whereDate(
                'target_pr.production_date',
                '<=',
                $filter->endDateString()
            );

        $this->applyRecordEligibility(
            query: $query,
            filter: $filter,
            recordAlias: 'target_pr',
            orderAlias: 'po'
        );

        $this->applyOrderFilters(
            query: $query,
            filter: $filter,
            recordAlias: 'target_pr'
        );

        $groupBy = [
            'pb.id',
            'po.id',
            'pb.quantity_unit',
            'pb.planned_quantity',
            'po.production_line_id',
            'pl.name',
            'po.product_id',
            'p.name',
            'p.product_family_id',
            'pf.name',
        ];

        $select = [
            'pb.id as batch_id',
            'po.id as order_id',
            'pb.quantity_unit as quantity_unit',
            'pb.planned_quantity as planned_quantity',
            'po.production_line_id as production_line_id',
            'pl.name as line_name',
            'po.product_id as product_id',
            'p.name as product_name',
            'p.product_family_id as product_family_id',
            'pf.name as family_name',
        ];

        if ($splitByShift) {
            $groupBy[] = DB::raw(
                'COALESCE(target_pr.shift_id, po.shift_id)'
            );
            $groupBy[] = 's.name';

            $select[] = DB::raw(
                'COALESCE(target_pr.shift_id, po.shift_id) as shift_id'
            );
            $select[] = 's.name as shift_name';
        } else {
            $select[] = DB::raw(
                'NULL as shift_id'
            );
            $select[] = DB::raw(
                'NULL as shift_name'
            );
        }

        return $query
            ->groupBy(...$groupBy)
            ->select($select)
            ->selectRaw(
                'MIN(target_pr.production_date) as metric_key'
            );
    }

    /**
     * @return list<object>
     */
    private function productionAggregation(
        AnalyticsFilter $filter,
        bool $includePending
    ): array {
        $query = DB::table('production_records as pr')
            ->join(
                'production_batches as pb',
                'pb.id',
                '=',
                'pr.production_batch_id'
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->where(function (Builder $eligible) use (
                $includePending
            ): void {
                $eligible->where(
                    'pr.validation_status',
                    ProductionValidationStatus::Validated->value
                );

                if ($includePending) {
                    $eligible->orWhere(
                        function (Builder $pending): void {
                            $pending
                                ->where(
                                    'pr.validation_status',
                                    ProductionValidationStatus::Pending->value
                                )
                                ->where(
                                    'po.status',
                                    ProductionOrderStatus::InProgress->value
                                );
                        }
                    );
                }
            })
            ->whereIn(
                'pr.import_status',
                $this->includedImportStatuses()
            )
            ->whereDate(
                'pr.production_date',
                '>=',
                $filter->startDateString()
            )
            ->whereDate(
                'pr.production_date',
                '<=',
                $filter->endDateString()
            );

        $this->applyOrderFilters(
            query: $query,
            filter: $filter,
            recordAlias: 'pr'
        );

        return $query
            ->groupBy('pr.quantity_unit')
            ->orderBy('pr.quantity_unit')
            ->selectRaw(
                'pr.quantity_unit as quantity_unit'
            )
            ->selectRaw(
                'COUNT(pr.id) as record_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN pr.validation_status = ? THEN 1 ELSE 0 END) as validated_record_count',
                [ProductionValidationStatus::Validated->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN pr.validation_status = ? THEN 1 ELSE 0 END) as provisional_record_count',
                [ProductionValidationStatus::Pending->value]
            )
            ->selectRaw(
                'COALESCE(SUM(pr.produced_quantity), 0) as actual_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.good_quantity), 0) as good_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.rejected_quantity), 0) as rejected_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.runtime_minutes), 0) as runtime_minutes'
            )
            ->selectRaw(
                'COALESCE(SUM(pr.downtime_minutes), 0) as downtime_minutes'
            )
            ->get()
            ->all();
    }

    private function catalogueQuery(
        AnalyticsFilter $filter
    ): Builder {
        $query = DB::table('production_orders as po')
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->leftJoin(
                'production_batches as catalogue_pb',
                'catalogue_pb.production_order_id',
                '=',
                'po.id'
            )
            ->leftJoin(
                'production_records as catalogue_pr',
                function (JoinClause $join) use (
                    $filter
                ): void {
                    $join
                        ->on(
                            'catalogue_pr.production_batch_id',
                            '=',
                            'catalogue_pb.id'
                        )
                        ->whereDate(
                            'catalogue_pr.production_date',
                            '>=',
                            $filter->startDateString()
                        )
                        ->whereDate(
                            'catalogue_pr.production_date',
                            '<=',
                            $filter->endDateString()
                        );
                }
            )
            ->whereIn(
                'po.import_status',
                $this->includedImportStatuses()
            )
            ->where(function (Builder $eligible) use (
                $filter
            ): void {
                $eligible
                    ->where(function (Builder $scheduled) use (
                        $filter
                    ): void {
                        $scheduled
                            ->where(
                                'po.planned_start_at',
                                '>=',
                                $filter->utcStart()
                                    ->toDateTimeString()
                            )
                            ->where(
                                'po.planned_start_at',
                                '<',
                                $filter->utcEndExclusive()
                                    ->toDateTimeString()
                            );
                    })
                    ->orWhereNotNull('catalogue_pr.id');
            });

        $this->applyStatusFilter($query, $filter);

        return $query;
    }

    private function applyOrderFilters(
        Builder $query,
        AnalyticsFilter $filter,
        bool $includeOrderId = true,
        ?string $recordAlias = null,
        bool $includeShift = true
    ): void {
        $this->applyStatusFilter($query, $filter);

        if ($filter->productionLineId !== null) {
            $lineIds = $this->equivalentIdsByName(
                table: 'production_lines',
                selectedId: $filter->productionLineId
            );

            if ($recordAlias === null) {
                $query->whereIn(
                    'po.production_line_id',
                    $lineIds
                );
            } else {
                $query->whereIn(
                    DB::raw(
                        "COALESCE({$recordAlias}.production_line_id, po.production_line_id)"
                    ),
                    $lineIds
                );
            }
        }

        if ($filter->productId !== null) {
            $query->whereIn(
                'po.product_id',
                $this->equivalentIdsByName(
                    table: 'products',
                    selectedId: $filter->productId
                )
            );
        }

        if ($filter->productFamilyId !== null) {
            $query->whereIn(
                'p.product_family_id',
                $this->equivalentIdsByName(
                    table: 'product_families',
                    selectedId: $filter->productFamilyId
                )
            );
        }

        if (
            $includeShift
            && $filter->shiftId !== null
        ) {
            $shiftIds = $this->equivalentShiftIds(
                $filter->shiftId
            );

            if ($recordAlias === null) {
                $query->whereIn('po.shift_id', $shiftIds);
            } else {
                $query->whereIn(
                    DB::raw(
                        "COALESCE({$recordAlias}.shift_id, po.shift_id)"
                    ),
                    $shiftIds
                );
            }
        }

        if (
            $includeOrderId
            && $filter->productionOrderId !== null
        ) {
            $query->where(
                'po.id',
                $filter->productionOrderId
            );
        }
    }

    private function applyStatusFilter(
        Builder $query,
        AnalyticsFilter $filter
    ): void {
        if ($filter->status !== null) {
            $query->where('po.status', $filter->status);

            return;
        }

        $query->whereIn(
            'po.status',
            [
                ProductionOrderStatus::InProgress->value,
                ProductionOrderStatus::Completed->value,
            ]
        );
    }

    /**
     * @return list<int>
     */
    private function equivalentIdsByName(
        string $table,
        int $selectedId
    ): array {
        $selectedName = DB::table($table)
            ->where('id', $selectedId)
            ->value('name');

        if (! is_string($selectedName)) {
            return [$selectedId];
        }

        $normalized = $this->normalizedName(
            $selectedName
        );

        $ids = DB::table($table)
            ->get(['id', 'name'])
            ->filter(
                fn (object $row): bool =>
                    $this->normalizedName($row->name)
                    === $normalized
            )
            ->pluck('id')
            ->map(
                static fn (mixed $id): int => (int) $id
            )
            ->all();

        return $ids === []
            ? [$selectedId]
            : array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    private function equivalentShiftIds(
        int $selectedShiftId
    ): array {
        $selected = DB::table('shifts')
            ->where('id', $selectedShiftId)
            ->first([
                'id',
                'name',
                'starts_at',
                'ends_at',
            ]);

        if ($selected === null) {
            return [$selectedShiftId];
        }

        $normalized = $this->normalizedName(
            $selected->name
        );

        $ids = DB::table('shifts')
            ->where('starts_at', $selected->starts_at)
            ->where('ends_at', $selected->ends_at)
            ->get(['id', 'name'])
            ->filter(
                fn (object $row): bool =>
                    $this->normalizedName($row->name)
                    === $normalized
            )
            ->pluck('id')
            ->map(
                static fn (mixed $id): int => (int) $id
            )
            ->all();

        return $ids === []
            ? [(int) $selected->id]
            : array_values(array_unique($ids));
    }

    private function canonicalizeOptions(
        Collection $rows,
        string $nameProperty
    ): Collection {
        return $rows
            ->groupBy(
                fn (object $row): string =>
                    $this->normalizedName(
                        $row->{$nameProperty}
                    )
            )
            ->map(
                fn (Collection $duplicates): object =>
                    $this->preferredOption($duplicates)
            )
            ->sortBy($nameProperty, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function preferredOption(
        Collection $duplicates
    ): object {
        return $duplicates
            ->sort(function (object $left, object $right): int {
                $countComparison =
                    ((int) ($right->order_count ?? 0))
                    <=>
                    ((int) ($left->order_count ?? 0));

                if ($countComparison !== 0) {
                    return $countComparison;
                }

                return ((int) $left->id)
                    <=> ((int) $right->id);
            })
            ->first();
    }

    private function normalizedName(
        mixed $value
    ): string {
        if (! is_string($value)) {
            return '';
        }

        $value = preg_replace(
            '/\s+/u',
            ' ',
            trim($value)
        );

        return mb_strtolower(
            is_string($value) ? $value : ''
        );
    }

    /**
     * @return list<string>
     */
    private function includedImportStatuses(): array
    {
        $statuses = config(
            'analytics.included_import_statuses',
            [
                'not_applicable',
                'imported',
                'skipped',
            ]
        );

        if (! is_array($statuses)) {
            return [
                'not_applicable',
                'imported',
                'skipped',
            ];
        }

        return array_values(
            array_filter(
                $statuses,
                static fn (mixed $status): bool =>
                    is_string($status)
                    && trim($status) !== ''
            )
        );
    }
}
