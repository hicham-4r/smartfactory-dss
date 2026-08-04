<?php

namespace App\Repositories;

use App\Contracts\Repositories\ErpQualityDataRepositoryInterface;
use App\Models\ErpFinishedLotRelease;
use App\Models\ErpQualityInspection;
use App\Models\ErpQualityTestResult;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentErpQualityDataRepository implements
    ErpQualityDataRepositoryInterface
{
    public function qualityInspections(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpQualityInspection::query()
            ->with([
                'product.family',
                'product.packagingFormat',
                'productionLine',
                'shift',
                'productionBatch',
                'testResults',
                'lotRelease',
            ])
            ->withCount('testResults')
            ->orderByDesc('sampled_at');

        $this->applyDateRange(
            $query,
            $filters,
            'sampled_at'
        );

        $this->applyUpdatedSince(
            $query,
            $filters,
            'source_updated_at'
        );

        $this->applyLateArrivalFilter(
            $query,
            $filters
        );

        $query->when(
            $filters['inspection_number'] ?? null,
            fn (
                Builder $query,
                string $inspectionNumber
            ) => $query->where(
                'inspection_number',
                $inspectionNumber
            )
        );

        $query->when(
            $filters['inspection_type'] ?? null,
            fn (
                Builder $query,
                string $inspectionType
            ) => $query->where(
                'inspection_type',
                $inspectionType
            )
        );

        $query->when(
            $filters['status'] ?? null,
            fn (Builder $query, string $status) =>
                $query->where('status', $status)
        );

        $query->when(
            $filters['result'] ?? null,
            fn (Builder $query, string $result) =>
                $query->where('result', $result)
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'product_code',
            'product'
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'line_code',
            'productionLine'
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'shift_code',
            'shift'
        );

        $this->applyBatchFilters(
            $query,
            $filters,
            'productionBatch'
        );

        $query->when(
            $filters['search'] ?? null,
            function (
                Builder $query,
                string $search
            ): void {
                $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where(
                                'inspection_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'inspector_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'nonconformity_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'nonconformity_description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'product',
                                fn (Builder $productQuery) =>
                                    $productQuery
                                        ->where(
                                            'code',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                            )
                            ->orWhereHas(
                                'productionBatch',
                                fn (Builder $batchQuery) =>
                                    $batchQuery
                                        ->where(
                                            'batch_number',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'lot_number',
                                            'like',
                                            "%{$search}%"
                                        )
                            );
                    }
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function qualityTestResults(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpQualityTestResult::query()
            ->with([
                'qualityInspection.product.family',
                'qualityInspection.product.packagingFormat',
                'qualityInspection.productionLine',
                'qualityInspection.shift',
                'qualityInspection.productionBatch',
                'qualityInspection.lotRelease',
            ])
            ->orderByDesc('tested_at');

        $this->applyDateRange(
            $query,
            $filters,
            'tested_at'
        );

        if (!empty($filters['updated_since'])) {
            $query->whereHas(
                'qualityInspection',
                fn (Builder $inspectionQuery) =>
                    $inspectionQuery->where(
                        'source_updated_at',
                        '>=',
                        $filters['updated_since']
                    )
            );
        }

        $query->when(
            $filters['test_code'] ?? null,
            fn (Builder $query, string $testCode) =>
                $query->where('test_code', $testCode)
        );

        $query->when(
            $filters['test_category'] ?? null,
            fn (
                Builder $query,
                string $testCategory
            ) => $query->where(
                'test_category',
                $testCategory
            )
        );

        $query->when(
            $filters['test_result'] ?? null,
            fn (Builder $query, string $testResult) =>
                $query->where('result', $testResult)
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'product_code',
            'qualityInspection.product'
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'line_code',
            'qualityInspection.productionLine'
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'shift_code',
            'qualityInspection.shift'
        );

        $this->applyBatchFilters(
            $query,
            $filters,
            'qualityInspection.productionBatch'
        );

        $query->when(
            $filters['inspection_number'] ?? null,
            function (
                Builder $query,
                string $inspectionNumber
            ): void {
                $query->whereHas(
                    'qualityInspection',
                    fn (Builder $inspectionQuery) =>
                        $inspectionQuery->where(
                            'inspection_number',
                            $inspectionNumber
                        )
                );
            }
        );

        $query->when(
            $filters['search'] ?? null,
            function (
                Builder $query,
                string $search
            ): void {
                $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where(
                                'test_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'test_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'test_category',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'text_value',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'qualityInspection',
                                fn (Builder $inspectionQuery) =>
                                    $inspectionQuery->where(
                                        'inspection_number',
                                        'like',
                                        "%{$search}%"
                                    )
                            );
                    }
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function finishedLotReleases(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpFinishedLotRelease::query()
            ->with([
                'productionBatch.productionOrder.product.family',
                'productionBatch.productionOrder.product.packagingFormat',
                'productionBatch.productionOrder.productionLine',
                'productionBatch.shift',
                'qualityInspection',
            ])
            ->orderByDesc('decision_at');

        $this->applyDateRange(
            $query,
            $filters,
            'decision_at'
        );

        $this->applyUpdatedSince(
            $query,
            $filters,
            'source_updated_at'
        );

        $this->applyLateArrivalFilter(
            $query,
            $filters
        );

        $query->when(
            $filters['release_number'] ?? null,
            fn (
                Builder $query,
                string $releaseNumber
            ) => $query->where(
                'release_number',
                $releaseNumber
            )
        );

        $query->when(
            $filters['decision'] ?? null,
            fn (Builder $query, string $decision) =>
                $query->where('decision', $decision)
        );

        $query->when(
            $filters['warehouse_status'] ?? null,
            fn (
                Builder $query,
                string $warehouseStatus
            ) => $query->where(
                'warehouse_status',
                $warehouseStatus
            )
        );

        $query->when(
            $filters['result'] ?? null,
            function (
                Builder $query,
                string $result
            ): void {
                $query->whereHas(
                    'qualityInspection',
                    fn (Builder $inspectionQuery) =>
                        $inspectionQuery->where(
                            'result',
                            $result
                        )
                );
            }
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'product_code',
            'productionBatch.productionOrder.product'
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'line_code',
            'productionBatch.productionOrder.productionLine'
        );

        $this->applyRelatedCodeFilter(
            $query,
            $filters,
            'shift_code',
            'productionBatch.shift'
        );

        $this->applyBatchFilters(
            $query,
            $filters,
            'productionBatch'
        );

        $query->when(
            $filters['inspection_number'] ?? null,
            function (
                Builder $query,
                string $inspectionNumber
            ): void {
                $query->whereHas(
                    'qualityInspection',
                    fn (Builder $inspectionQuery) =>
                        $inspectionQuery->where(
                            'inspection_number',
                            $inspectionNumber
                        )
                );
            }
        );

        $query->when(
            $filters['search'] ?? null,
            function (
                Builder $query,
                string $search
            ): void {
                $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where(
                                'release_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'lot_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'quality_certificate_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'released_by',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'decision_reason',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /**
     * Failed inspections exposed as canonical nonconformity records.
     *
     * @param array<string, mixed> $filters
     */
    public function nonconformities(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpQualityInspection::query()
            ->with([
                'productionBatch',
            ])
            ->whereNotNull(
                'nonconformity_code'
            )
            ->where(
                'result',
                'failed'
            )
            ->orderByDesc(
                'inspection_completed_at'
            );

        $this->applyDateRange(
            $query,
            $filters,
            'inspection_completed_at'
        );

        $this->applyUpdatedSince(
            $query,
            $filters,
            'source_updated_at'
        );

        $this->applyLateArrivalFilter(
            $query,
            $filters
        );

        $this->applyBatchFilters(
            $query,
            $filters,
            'productionBatch'
        );

        $query->when(
            $filters['inspection_number']
                ?? null,
            fn (
                Builder $query,
                string $inspectionNumber
            ) => $query->where(
                'inspection_number',
                $inspectionNumber
            )
        );

        $query->when(
            $filters['search'] ?? null,
            function (
                Builder $query,
                string $search
            ): void {
                $query->where(
                    function (
                        Builder $query
                    ) use ($search): void {
                        $query
                            ->where(
                                'inspection_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'nonconformity_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'nonconformity_description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'corrective_action',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'productionBatch',
                                fn (
                                    Builder $batchQuery
                                ) =>
                                    $batchQuery
                                        ->where(
                                            'batch_number',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'lot_number',
                                            'like',
                                            "%{$search}%"
                                        )
                            );
                    }
                );
            }
        );

        return $query
            ->paginate(
                $filters['per_page']
            )
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyDateRange(
        Builder $query,
        array $filters,
        string $column
    ): void {
        if (!empty($filters['date_from'])) {
            $query->where(
                $column,
                '>=',
                $filters['date_from'] . ' 00:00:00'
            );
        }

        if (!empty($filters['date_to'])) {
            $query->where(
                $column,
                '<=',
                $filters['date_to'] . ' 23:59:59'
            );
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyUpdatedSince(
        Builder $query,
        array $filters,
        string $column
    ): void {
        if (!empty($filters['updated_since'])) {
            $query->where(
                $column,
                '>=',
                $filters['updated_since']
            );
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyLateArrivalFilter(
        Builder $query,
        array $filters
    ): void {
        if (array_key_exists('is_late_arrival', $filters)) {
            $query->where(
                'is_late_arrival',
                $filters['is_late_arrival']
            );
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyRelatedCodeFilter(
        Builder $query,
        array $filters,
        string $filterKey,
        string $relationship
    ): void {
        if (empty($filters[$filterKey])) {
            return;
        }

        $code = $filters[$filterKey];

        $query->whereHas(
            $relationship,
            fn (Builder $relatedQuery) =>
                $relatedQuery->where('code', $code)
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyBatchFilters(
        Builder $query,
        array $filters,
        string $relationship
    ): void {
        if (!empty($filters['batch_number'])) {
            $batchNumber = $filters['batch_number'];

            $query->whereHas(
                $relationship,
                fn (Builder $batchQuery) =>
                    $batchQuery->where(
                        'batch_number',
                        $batchNumber
                    )
            );
        }

        if (!empty($filters['lot_number'])) {
            $lotNumber = $filters['lot_number'];

            $query->whereHas(
                $relationship,
                fn (Builder $batchQuery) =>
                    $batchQuery->where(
                        'lot_number',
                        $lotNumber
                    )
            );
        }
    }
}
