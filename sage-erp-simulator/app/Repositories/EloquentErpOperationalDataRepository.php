<?php

namespace App\Repositories;

use App\Contracts\Repositories\ErpOperationalDataRepositoryInterface;
use App\Models\ErpProductionBatch;
use App\Models\ErpProductionOrder;
use App\Models\ErpProductionRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentErpOperationalDataRepository implements
    ErpOperationalDataRepositoryInterface
{
    public function productionOrders(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpProductionOrder::query()
            ->with([
                'product.family',
                'product.packagingFormat',
                'productionLine',
            ])
            ->withCount('batches')
            ->orderByDesc('planned_start_at')
            ->orderByDesc('id');

        $this->applyDateRange(
            $query,
            $filters,
            'planned_start_at'
        );

        $this->applyUpdatedSince(
            $query,
            $filters,
            'updated_at'
        );

        $query->when(
            $filters['status'] ?? null,
            fn (Builder $query, string $status) =>
                $query->where('status', $status)
        );

        $query->when(
            $filters['order_number'] ?? null,
            fn (Builder $query, string $orderNumber) =>
                $query->where(
                    'order_number',
                    $orderNumber
                )
        );

        $query->when(
            $filters['product_code'] ?? null,
            function (
                Builder $query,
                string $productCode
            ): void {
                $query->whereHas(
                    'product',
                    fn (Builder $productQuery) =>
                        $productQuery->where(
                            'code',
                            $productCode
                        )
                );
            }
        );

        $query->when(
            $filters['line_code'] ?? null,
            function (
                Builder $query,
                string $lineCode
            ): void {
                $query->whereHas(
                    'productionLine',
                    fn (Builder $lineQuery) =>
                        $lineQuery->where(
                            'code',
                            $lineCode
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
                                'order_number',
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
                            );
                    }
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function productionBatches(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpProductionBatch::query()
            ->with([
                'productionOrder.product.family',
                'productionOrder.product.packagingFormat',
                'productionOrder.productionLine',
                'shift',
                'operatorAssignment.operator',
            ])
            ->withCount('records')
            ->orderByDesc('scheduled_start_at')
            ->orderByDesc('id');

        $this->applyDateRange(
            $query,
            $filters,
            'scheduled_start_at'
        );

        $this->applyUpdatedSince(
            $query,
            $filters,
            'updated_at'
        );

        $query->when(
            $filters['status'] ?? null,
            fn (Builder $query, string $status) =>
                $query->where('status', $status)
        );

        $query->when(
            $filters['quality_status'] ?? null,
            fn (Builder $query, string $qualityStatus) =>
                $query->where(
                    'quality_status',
                    $qualityStatus
                )
        );

        $query->when(
            $filters['batch_number'] ?? null,
            fn (Builder $query, string $batchNumber) =>
                $query->where(
                    'batch_number',
                    $batchNumber
                )
        );

        $query->when(
            $filters['lot_number'] ?? null,
            fn (Builder $query, string $lotNumber) =>
                $query->where(
                    'lot_number',
                    $lotNumber
                )
        );

        $query->when(
            $filters['order_number'] ?? null,
            function (
                Builder $query,
                string $orderNumber
            ): void {
                $query->whereHas(
                    'productionOrder',
                    fn (Builder $orderQuery) =>
                        $orderQuery->where(
                            'order_number',
                            $orderNumber
                        )
                );
            }
        );

        $query->when(
            $filters['product_code'] ?? null,
            function (
                Builder $query,
                string $productCode
            ): void {
                $query->whereHas(
                    'productionOrder.product',
                    fn (Builder $productQuery) =>
                        $productQuery->where(
                            'code',
                            $productCode
                        )
                );
            }
        );

        $query->when(
            $filters['line_code'] ?? null,
            function (
                Builder $query,
                string $lineCode
            ): void {
                $query->whereHas(
                    'productionOrder.productionLine',
                    fn (Builder $lineQuery) =>
                        $lineQuery->where(
                            'code',
                            $lineCode
                        )
                );
            }
        );

        $query->when(
            $filters['shift_code'] ?? null,
            function (
                Builder $query,
                string $shiftCode
            ): void {
                $query->whereHas(
                    'shift',
                    fn (Builder $shiftQuery) =>
                        $shiftQuery->where(
                            'code',
                            $shiftCode
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
                                'batch_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'lot_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'productionOrder',
                                fn (Builder $orderQuery) =>
                                    $orderQuery->where(
                                        'order_number',
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

    public function productionRecords(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpProductionRecord::query()
            ->with([
                'productionBatch.productionOrder.product.family',
                'productionBatch.productionOrder.product.packagingFormat',
                'productionBatch.productionOrder.productionLine',
                'productionBatch.shift',
                'machine',
                'processStage',
            ])
            ->orderByDesc('interval_start_at')
            ->orderByDesc('id');

        $this->applyDateRange(
            $query,
            $filters,
            'interval_start_at'
        );

        /*
         * Production records use source_updated_at for
         * incremental ERP synchronization.
         */
        $this->applyUpdatedSince(
            $query,
            $filters,
            'source_updated_at'
        );

        if (array_key_exists('is_late_arrival', $filters)) {
            $query->where(
                'is_late_arrival',
                $filters['is_late_arrival']
            );
        }

        $query->when(
            $filters['batch_number'] ?? null,
            function (
                Builder $query,
                string $batchNumber
            ): void {
                $query->whereHas(
                    'productionBatch',
                    fn (Builder $batchQuery) =>
                        $batchQuery->where(
                            'batch_number',
                            $batchNumber
                        )
                );
            }
        );

        $query->when(
            $filters['lot_number'] ?? null,
            function (
                Builder $query,
                string $lotNumber
            ): void {
                $query->whereHas(
                    'productionBatch',
                    fn (Builder $batchQuery) =>
                        $batchQuery->where(
                            'lot_number',
                            $lotNumber
                        )
                );
            }
        );

        $query->when(
            $filters['order_number'] ?? null,
            function (
                Builder $query,
                string $orderNumber
            ): void {
                $query->whereHas(
                    'productionBatch.productionOrder',
                    fn (Builder $orderQuery) =>
                        $orderQuery->where(
                            'order_number',
                            $orderNumber
                        )
                );
            }
        );

        $query->when(
            $filters['product_code'] ?? null,
            function (
                Builder $query,
                string $productCode
            ): void {
                $query->whereHas(
                    'productionBatch.productionOrder.product',
                    fn (Builder $productQuery) =>
                        $productQuery->where(
                            'code',
                            $productCode
                        )
                );
            }
        );

        $query->when(
            $filters['line_code'] ?? null,
            function (
                Builder $query,
                string $lineCode
            ): void {
                $query->whereHas(
                    'productionBatch.productionOrder.productionLine',
                    fn (Builder $lineQuery) =>
                        $lineQuery->where(
                            'code',
                            $lineCode
                        )
                );
            }
        );

        $query->when(
            $filters['shift_code'] ?? null,
            function (
                Builder $query,
                string $shiftCode
            ): void {
                $query->whereHas(
                    'productionBatch.shift',
                    fn (Builder $shiftQuery) =>
                        $shiftQuery->where(
                            'code',
                            $shiftCode
                        )
                );
            }
        );

        $query->when(
            $filters['machine_code'] ?? null,
            function (
                Builder $query,
                string $machineCode
            ): void {
                $query->whereHas(
                    'machine',
                    fn (Builder $machineQuery) =>
                        $machineQuery->where(
                            'code',
                            $machineCode
                        )
                );
            }
        );

        $query->when(
            $filters['process_stage_code'] ?? null,
            function (
                Builder $query,
                string $stageCode
            ): void {
                $query->whereHas(
                    'processStage',
                    fn (Builder $stageQuery) =>
                        $stageQuery->where(
                            'code',
                            $stageCode
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
                                'record_number',
                                'like',
                                "%{$search}%"
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
                $filters['date_from']
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
}