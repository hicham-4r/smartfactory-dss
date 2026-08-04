<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\Enums\Analytics\QualityBreakdownDimension;
use App\Enums\ERP\ErpFinishedLotStatus;
use App\Enums\ERP\ErpInspectionResult;
use App\Enums\ERP\ErpNonconformitySeverity;
use App\Enums\ERP\ErpNonconformityStatus;
use App\Repositories\Contracts\QualityAnalyticsRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentQualityAnalyticsRepository implements
    QualityAnalyticsRepositoryInterface
{
    public function inspectionTotals(
        QualityAnalyticsFilter $filter
    ): object {
        return $this->inspectionBase($filter)
            ->selectRaw('COUNT(DISTINCT i.id) as inspection_count')
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as passed_inspection_count",
                [ErpInspectionResult::Passed->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as failed_inspection_count",
                [ErpInspectionResult::Failed->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as conditional_inspection_count",
                [ErpInspectionResult::Conditional->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as pending_inspection_count",
                [ErpInspectionResult::Pending->value]
            )
            ->selectRaw('COALESCE(SUM(i.sample_size), 0) as sample_size')
            ->selectRaw(
                'COALESCE(SUM(i.passed_quantity), 0) as passed_sample_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(i.failed_quantity), 0) as failed_sample_quantity'
            )
            ->first();
    }

    public function lotTotals(
        QualityAnalyticsFilter $filter
    ): object {
        return $this->lotBase($filter)
            ->selectRaw('COUNT(DISTINCT fl.id) as lot_count')
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as released_lot_count",
                [ErpFinishedLotStatus::Released->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as blocked_lot_count",
                [ErpFinishedLotStatus::Blocked->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as rejected_lot_count",
                [ErpFinishedLotStatus::Rejected->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as pending_lot_count",
                [ErpFinishedLotStatus::Pending->value]
            )
            ->first();
    }

    public function nonconformityTotals(
        QualityAnalyticsFilter $filter
    ): object {
        return $this->nonconformityBase($filter)
            ->selectRaw('COUNT(DISTINCT nc.id) as nonconformity_count')
            ->selectRaw(
                "SUM(CASE WHEN nc.status IN (?, ?) THEN 1 ELSE 0 END) as open_nonconformity_count",
                [
                    ErpNonconformityStatus::Open->value,
                    ErpNonconformityStatus::Investigating->value,
                ]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.status IN (?, ?) THEN 1 ELSE 0 END) as resolved_nonconformity_count",
                [
                    ErpNonconformityStatus::Corrected->value,
                    ErpNonconformityStatus::Closed->value,
                ]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.severity = ? THEN 1 ELSE 0 END) as minor_nonconformity_count",
                [ErpNonconformitySeverity::Minor->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.severity = ? THEN 1 ELSE 0 END) as major_nonconformity_count",
                [ErpNonconformitySeverity::Major->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.severity = ? THEN 1 ELSE 0 END) as critical_nonconformity_count",
                [ErpNonconformitySeverity::Critical->value]
            )
            ->first();
    }

    public function lotQuantitiesByUnit(
        QualityAnalyticsFilter $filter
    ): array {
        return $this->lotBase($filter)
            ->groupBy('fl.quantity_unit')
            ->orderBy('fl.quantity_unit')
            ->selectRaw('fl.quantity_unit as quantity_unit')
            ->selectRaw('COUNT(DISTINCT fl.id) as lot_count')
            ->selectRaw(
                'COALESCE(SUM(fl.produced_quantity), 0) as produced_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(fl.released_quantity), 0) as released_quantity'
            )
            ->selectRaw(
                'COALESCE(SUM(fl.rejected_quantity), 0) as rejected_quantity'
            )
            ->get()
            ->all();
    }

    public function inspectionsByDimension(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array {
        [$id, $label] = $this->dimensionColumns($dimension);

        return $this->inspectionBase($filter)
            ->groupBy($id, $label)
            ->orderBy($label)
            ->selectRaw("{$id} as dimension_id")
            ->selectRaw("{$label} as dimension_label")
            ->selectRaw('COUNT(DISTINCT i.id) as inspection_count')
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as passed_inspection_count",
                [ErpInspectionResult::Passed->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as failed_inspection_count",
                [ErpInspectionResult::Failed->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as conditional_inspection_count",
                [ErpInspectionResult::Conditional->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN i.result = ? THEN 1 ELSE 0 END) as pending_inspection_count",
                [ErpInspectionResult::Pending->value]
            )
            ->get()
            ->all();
    }

    public function lotsByDimension(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array {
        [$id, $label] = $this->dimensionColumns($dimension);

        return $this->lotBase($filter)
            ->groupBy($id, $label)
            ->orderBy($label)
            ->selectRaw("{$id} as dimension_id")
            ->selectRaw("{$label} as dimension_label")
            ->selectRaw('COUNT(DISTINCT fl.id) as lot_count')
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as released_lot_count",
                [ErpFinishedLotStatus::Released->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as blocked_lot_count",
                [ErpFinishedLotStatus::Blocked->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as rejected_lot_count",
                [ErpFinishedLotStatus::Rejected->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN fl.status = ? THEN 1 ELSE 0 END) as pending_lot_count",
                [ErpFinishedLotStatus::Pending->value]
            )
            ->get()
            ->all();
    }

    public function nonconformitiesByDimension(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array {
        [$id, $label] = $this->dimensionColumns($dimension);

        return $this->nonconformityBase($filter)
            ->groupBy($id, $label)
            ->orderBy($label)
            ->selectRaw("{$id} as dimension_id")
            ->selectRaw("{$label} as dimension_label")
            ->selectRaw('COUNT(DISTINCT nc.id) as nonconformity_count')
            ->selectRaw(
                "SUM(CASE WHEN nc.status IN (?, ?) THEN 1 ELSE 0 END) as open_nonconformity_count",
                [
                    ErpNonconformityStatus::Open->value,
                    ErpNonconformityStatus::Investigating->value,
                ]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.status IN (?, ?) THEN 1 ELSE 0 END) as resolved_nonconformity_count",
                [
                    ErpNonconformityStatus::Corrected->value,
                    ErpNonconformityStatus::Closed->value,
                ]
            )
            ->get()
            ->all();
    }

    public function nonconformityCategories(
        QualityAnalyticsFilter $filter
    ): array {
        return $this->nonconformityBase($filter)
            ->groupBy('nc.category')
            ->orderByDesc('nonconformity_count')
            ->orderBy('nc.category')
            ->selectRaw('nc.category as category')
            ->selectRaw('COUNT(DISTINCT nc.id) as nonconformity_count')
            ->selectRaw(
                "SUM(CASE WHEN nc.status IN (?, ?) THEN 1 ELSE 0 END) as open_count",
                [
                    ErpNonconformityStatus::Open->value,
                    ErpNonconformityStatus::Investigating->value,
                ]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.status IN (?, ?) THEN 1 ELSE 0 END) as resolved_count",
                [
                    ErpNonconformityStatus::Corrected->value,
                    ErpNonconformityStatus::Closed->value,
                ]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.severity = ? THEN 1 ELSE 0 END) as minor_count",
                [ErpNonconformitySeverity::Minor->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.severity = ? THEN 1 ELSE 0 END) as major_count",
                [ErpNonconformitySeverity::Major->value]
            )
            ->selectRaw(
                "SUM(CASE WHEN nc.severity = ? THEN 1 ELSE 0 END) as critical_count",
                [ErpNonconformitySeverity::Critical->value]
            )
            ->get()
            ->all();
    }

    public function filterableProductionLines(
        QualityAnalyticsFilter $filter
    ): Collection {
        $ids = collect($this->filterCompatibilityRows($filter))
            ->pluck('production_line_id')
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : DB::table('production_lines')
                ->whereIn('id', $ids->all())
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
    }

    public function filterableProductFamilies(
        QualityAnalyticsFilter $filter
    ): Collection {
        $ids = collect($this->filterCompatibilityRows($filter))
            ->pluck('product_family_id')
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? collect()
            : DB::table('product_families')
                ->whereIn('id', $ids->all())
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
    }

    public function filterableProducts(
        QualityAnalyticsFilter $filter
    ): Collection {
        $rows = collect($this->filterCompatibilityRows($filter));
        $ids = $rows->pluck('product_id')->unique()->values();

        return $ids->isEmpty()
            ? collect()
            : DB::table('products')
                ->whereIn('id', $ids->all())
                ->orderBy('name')
                ->get([
                    'id',
                    'product_family_id',
                    'name',
                    'code',
                ]);
    }

    public function filterCompatibilityRows(
        QualityAnalyticsFilter $filter
    ): array {
        /*
         * Catalogue choices are constrained by period and quality-status
         * filters, but not by the currently selected line/product/family.
         * This lets users switch dimensions without first resetting the form.
         */
        $catalogueFilter = $this->withoutDimensionFilters($filter);

        $select = [
            'pl.id as production_line_id',
            'pf.id as product_family_id',
            'p.id as product_id',
        ];

        return collect()
            ->merge(
                $this->inspectionBase($catalogueFilter)
                    ->distinct()
                    ->get($select)
            )
            ->merge(
                $this->lotBase($catalogueFilter)
                    ->distinct()
                    ->get($select)
            )
            ->merge(
                $this->nonconformityBase($catalogueFilter)
                    ->distinct()
                    ->get($select)
            )
            ->map(
                static fn (object $row): array => [
                    'production_line_id' =>
                        (int) $row->production_line_id,
                    'product_family_id' =>
                        (int) $row->product_family_id,
                    'product_id' => (int) $row->product_id,
                ]
            )
            ->unique(
                static fn (array $row): string =>
                    $row['production_line_id'].'|'
                    .$row['product_family_id'].'|'
                    .$row['product_id']
            )
            ->sortBy([
                ['production_line_id', 'asc'],
                ['product_family_id', 'asc'],
                ['product_id', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function inspectionBase(
        QualityAnalyticsFilter $filter
    ): Builder {
        $query = DB::table('inspections as i');
        $this->joinContext($query, 'i.batch_external_id');

        $query
            ->whereIn('i.import_status', $this->includedImportStatuses())
            ->where('i.inspected_at', '>=', $this->utcStart($filter))
            ->where('i.inspected_at', '<', $this->utcEnd($filter));

        $this->applyDimensionFilters($query, $filter);

        if ($filter->inspectionResult !== null) {
            $query->where('i.result', $filter->inspectionResult);
        }

        if ($filter->lotStatus !== null || $filter->lotNumber !== null) {
            $query->whereExists(
                function (Builder $lot) use ($filter): void {
                    $lot
                        ->selectRaw('1')
                        ->from('finished_lots as filter_fl')
                        ->whereColumn(
                            'filter_fl.batch_external_id',
                            'i.batch_external_id'
                        )
                        ->whereIn(
                            'filter_fl.import_status',
                            $this->includedImportStatuses()
                        )
                        ->where(
                            'filter_fl.produced_at',
                            '>=',
                            $this->utcStart($filter)
                        )
                        ->where(
                            'filter_fl.produced_at',
                            '<',
                            $this->utcEnd($filter)
                        );

                    if ($filter->lotStatus !== null) {
                        $lot->where('filter_fl.status', $filter->lotStatus);
                    }

                    if ($filter->lotNumber !== null) {
                        $lot->where(
                            'filter_fl.lot_number',
                            'like',
                            '%'.$filter->lotNumber.'%'
                        );
                    }
                }
            );
        }

        if (
            $filter->nonconformitySeverity !== null
            || $filter->nonconformityStatus !== null
        ) {
            $query->whereExists(
                function (Builder $nc) use ($filter): void {
                    $nc
                        ->selectRaw('1')
                        ->from('nonconformities as filter_nc')
                        ->whereColumn(
                            'filter_nc.inspection_external_id',
                            'i.external_id'
                        )
                        ->whereIn(
                            'filter_nc.import_status',
                            $this->includedImportStatuses()
                        )
                        ->where(
                            'filter_nc.detected_at',
                            '>=',
                            $this->utcStart($filter)
                        )
                        ->where(
                            'filter_nc.detected_at',
                            '<',
                            $this->utcEnd($filter)
                        );

                    if ($filter->nonconformitySeverity !== null) {
                        $nc->where(
                            'filter_nc.severity',
                            $filter->nonconformitySeverity
                        );
                    }

                    if ($filter->nonconformityStatus !== null) {
                        $nc->where(
                            'filter_nc.status',
                            $filter->nonconformityStatus
                        );
                    }
                }
            );
        }

        return $query;
    }

    private function lotBase(
        QualityAnalyticsFilter $filter
    ): Builder {
        $query = DB::table('finished_lots as fl');
        $this->joinContext($query, 'fl.batch_external_id');

        $query
            ->whereIn('fl.import_status', $this->includedImportStatuses())
            ->where('fl.produced_at', '>=', $this->utcStart($filter))
            ->where('fl.produced_at', '<', $this->utcEnd($filter));

        $this->applyDimensionFilters($query, $filter);

        if ($filter->lotStatus !== null) {
            $query->where('fl.status', $filter->lotStatus);
        }

        if ($filter->lotNumber !== null) {
            $query->where(
                'fl.lot_number',
                'like',
                '%'.$filter->lotNumber.'%'
            );
        }

        if ($filter->inspectionResult !== null) {
            $query->whereExists(
                function (Builder $inspection) use ($filter): void {
                    $inspection
                        ->selectRaw('1')
                        ->from('inspections as filter_i')
                        ->whereColumn(
                            'filter_i.batch_external_id',
                            'fl.batch_external_id'
                        )
                        ->whereIn(
                            'filter_i.import_status',
                            $this->includedImportStatuses()
                        )
                        ->where(
                            'filter_i.inspected_at',
                            '>=',
                            $this->utcStart($filter)
                        )
                        ->where(
                            'filter_i.inspected_at',
                            '<',
                            $this->utcEnd($filter)
                        )
                        ->where(
                            'filter_i.result',
                            $filter->inspectionResult
                        );
                }
            );
        }

        if (
            $filter->nonconformitySeverity !== null
            || $filter->nonconformityStatus !== null
        ) {
            $query->whereExists(
                function (Builder $nc) use ($filter): void {
                    $nc
                        ->selectRaw('1')
                        ->from('nonconformities as filter_nc')
                        ->join(
                            'inspections as filter_i',
                            'filter_i.external_id',
                            '=',
                            'filter_nc.inspection_external_id'
                        )
                        ->whereColumn(
                            'filter_i.batch_external_id',
                            'fl.batch_external_id'
                        )
                        ->whereIn(
                            'filter_nc.import_status',
                            $this->includedImportStatuses()
                        )
                        ->where(
                            'filter_nc.detected_at',
                            '>=',
                            $this->utcStart($filter)
                        )
                        ->where(
                            'filter_nc.detected_at',
                            '<',
                            $this->utcEnd($filter)
                        );

                    if ($filter->nonconformitySeverity !== null) {
                        $nc->where(
                            'filter_nc.severity',
                            $filter->nonconformitySeverity
                        );
                    }

                    if ($filter->nonconformityStatus !== null) {
                        $nc->where(
                            'filter_nc.status',
                            $filter->nonconformityStatus
                        );
                    }
                }
            );
        }

        return $query;
    }

    private function nonconformityBase(
        QualityAnalyticsFilter $filter
    ): Builder {
        $query = DB::table('nonconformities as nc')
            ->join(
                'inspections as i',
                'i.external_id',
                '=',
                'nc.inspection_external_id'
            );

        $this->joinContext($query, 'i.batch_external_id');

        $query
            ->whereIn('nc.import_status', $this->includedImportStatuses())
            ->whereIn('i.import_status', $this->includedImportStatuses())
            ->where('nc.detected_at', '>=', $this->utcStart($filter))
            ->where('nc.detected_at', '<', $this->utcEnd($filter));

        $this->applyDimensionFilters($query, $filter);

        if ($filter->inspectionResult !== null) {
            $query->where('i.result', $filter->inspectionResult);
        }

        if ($filter->nonconformitySeverity !== null) {
            $query->where('nc.severity', $filter->nonconformitySeverity);
        }

        if ($filter->nonconformityStatus !== null) {
            $query->where('nc.status', $filter->nonconformityStatus);
        }

        if ($filter->lotStatus !== null || $filter->lotNumber !== null) {
            $query->whereExists(
                function (Builder $lot) use ($filter): void {
                    $lot
                        ->selectRaw('1')
                        ->from('finished_lots as filter_fl')
                        ->whereColumn(
                            'filter_fl.batch_external_id',
                            'i.batch_external_id'
                        )
                        ->whereIn(
                            'filter_fl.import_status',
                            $this->includedImportStatuses()
                        )
                        ->where(
                            'filter_fl.produced_at',
                            '>=',
                            $this->utcStart($filter)
                        )
                        ->where(
                            'filter_fl.produced_at',
                            '<',
                            $this->utcEnd($filter)
                        );

                    if ($filter->lotStatus !== null) {
                        $lot->where('filter_fl.status', $filter->lotStatus);
                    }

                    if ($filter->lotNumber !== null) {
                        $lot->where(
                            'filter_fl.lot_number',
                            'like',
                            '%'.$filter->lotNumber.'%'
                        );
                    }
                }
            );
        }

        return $query;
    }

    private function joinContext(
        Builder $query,
        string $batchExternalColumn,
    ): void {
        $query
            ->join(
                'production_batches as pb',
                'pb.external_id',
                '=',
                $batchExternalColumn
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join('products as p', 'p.id', '=', 'po.product_id')
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
            ->whereIn('pb.import_status', $this->includedImportStatuses())
            ->whereIn('po.import_status', $this->includedImportStatuses());
    }

    private function applyDimensionFilters(
        Builder $query,
        QualityAnalyticsFilter $filter,
    ): void {
        if ($filter->productionLineId !== null) {
            $query->where('pl.id', $filter->productionLineId);
        }

        if ($filter->productId !== null) {
            $query->where('p.id', $filter->productId);
        }

        if ($filter->productFamilyId !== null) {
            $query->where('pf.id', $filter->productFamilyId);
        }
    }

    /** @return array{0:string,1:string} */
    private function dimensionColumns(
        QualityBreakdownDimension $dimension
    ): array {
        return match ($dimension) {
            QualityBreakdownDimension::ProductionLine => [
                'pl.id',
                'pl.name',
            ],
            QualityBreakdownDimension::Product => [
                'p.id',
                'p.name',
            ],
            QualityBreakdownDimension::ProductFamily => [
                'pf.id',
                'pf.name',
            ],
        };
    }

    private function withoutDimensionFilters(
        QualityAnalyticsFilter $filter
    ): QualityAnalyticsFilter {
        return new QualityAnalyticsFilter(
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            timezone: $filter->timezone,
            inspectionResult: $filter->inspectionResult,
            lotStatus: $filter->lotStatus,
            nonconformitySeverity: $filter->nonconformitySeverity,
            nonconformityStatus: $filter->nonconformityStatus,
            lotNumber: $filter->lotNumber,
        );
    }

    /** @return list<string> */
    private function includedImportStatuses(): array
    {
        return [
            'imported',
            'not_applicable',
        ];
    }

    private function utcStart(QualityAnalyticsFilter $filter): string
    {
        return $filter->utcStart()->toDateTimeString();
    }

    private function utcEnd(QualityAnalyticsFilter $filter): string
    {
        return $filter->utcEndExclusive()->toDateTimeString();
    }
}
