<?php

namespace App\Repositories;

use App\Contracts\Repositories\ErpMaintenanceDataRepositoryInterface;
use App\Models\ErpDowntimeEvent;
use App\Models\ErpMachineStatusEvent;
use App\Models\ErpMaintenanceHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentErpMaintenanceDataRepository implements
    ErpMaintenanceDataRepositoryInterface
{
    public function downtimeEvents(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpDowntimeEvent::query()
            ->with([
                'machine',
                'productionLine',
                'shift',
                'productionBatch.productionOrder.product',
                'maintenanceRecord',
            ])
            ->orderByDesc('started_at');

        $this->applyDateRange(
            $query,
            $filters,
            'started_at'
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
            $filters['category'] ?? null,
            fn (Builder $query, string $category) =>
                $query->where('category', $category)
        );

        $query->when(
            $filters['downtime_type'] ?? null,
            fn (Builder $query, string $downtimeType) =>
                $query->where(
                    'downtime_type',
                    $downtimeType
                )
        );

        $query->when(
            $filters['status'] ?? null,
            fn (Builder $query, string $status) =>
                $query->where('status', $status)
        );

        $query->when(
            $filters['event_number'] ?? null,
            fn (Builder $query, string $eventNumber) =>
                $query->where(
                    'event_number',
                    $eventNumber
                )
        );

        $query->when(
            $filters['reason_code'] ?? null,
            fn (Builder $query, string $reasonCode) =>
                $query->where(
                    'reason_code',
                    $reasonCode
                )
        );

        $this->applyMachineFilter(
            $query,
            $filters
        );

        $this->applyLineFilter(
            $query,
            $filters
        );

        $this->applyShiftFilter(
            $query,
            $filters
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
                                'event_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'reason_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'reason_description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'downtime_type',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'machine',
                                fn (Builder $machineQuery) =>
                                    $machineQuery
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

    public function machineStatusEvents(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpMachineStatusEvent::query()
            ->with([
                'machine',
                'productionLine',
                'shift',
            ])
            ->orderByDesc('started_at');

        $this->applyDateRange(
            $query,
            $filters,
            'started_at'
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
            $filters['status_code'] ?? null,
            fn (Builder $query, string $statusCode) =>
                $query->where(
                    'status_code',
                    $statusCode
                )
        );

        $query->when(
            $filters['status_event_number'] ?? null,
            fn (
                Builder $query,
                string $statusEventNumber
            ) => $query->where(
                'status_event_number',
                $statusEventNumber
            )
        );

        $this->applyMachineFilter(
            $query,
            $filters
        );

        $this->applyLineFilter(
            $query,
            $filters
        );

        $this->applyShiftFilter(
            $query,
            $filters
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
                                'status_event_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'status_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'notes',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'machine',
                                fn (Builder $machineQuery) =>
                                    $machineQuery
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

    public function maintenanceHistory(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpMaintenanceHistory::query()
            ->with([
                'machine',
                'productionLine',
                'downtimeEvent.shift',
            ])
            ->orderByDesc('started_at');

        $this->applyDateRange(
            $query,
            $filters,
            'started_at'
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
            $filters['maintenance_type'] ?? null,
            fn (
                Builder $query,
                string $maintenanceType
            ) => $query->where(
                'maintenance_type',
                $maintenanceType
            )
        );

        $query->when(
            $filters['priority'] ?? null,
            fn (Builder $query, string $priority) =>
                $query->where('priority', $priority)
        );

        $query->when(
            $filters['status'] ?? null,
            fn (Builder $query, string $status) =>
                $query->where('status', $status)
        );

        $query->when(
            $filters['maintenance_number'] ?? null,
            fn (
                Builder $query,
                string $maintenanceNumber
            ) => $query->where(
                'maintenance_number',
                $maintenanceNumber
            )
        );

        $query->when(
            $filters['failure_code'] ?? null,
            fn (Builder $query, string $failureCode) =>
                $query->where(
                    'failure_code',
                    $failureCode
                )
        );

        $query->when(
            $filters['downtime_type'] ?? null,
            function (
                Builder $query,
                string $downtimeType
            ): void {
                $query->whereHas(
                    'downtimeEvent',
                    fn (Builder $downtimeQuery) =>
                        $downtimeQuery->where(
                            'downtime_type',
                            $downtimeType
                        )
                );
            }
        );

        $this->applyMachineFilter(
            $query,
            $filters
        );

        $this->applyLineFilter(
            $query,
            $filters
        );

        $query->when(
            $filters['shift_code'] ?? null,
            function (
                Builder $query,
                string $shiftCode
            ): void {
                $query->whereHas(
                    'downtimeEvent.shift',
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
                                'maintenance_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'failure_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'failure_description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'root_cause',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'actions_taken',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'technician_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'machine',
                                fn (Builder $machineQuery) =>
                                    $machineQuery
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
    private function applyMachineFilter(
        Builder $query,
        array $filters
    ): void {
        if (empty($filters['machine_code'])) {
            return;
        }

        $machineCode = $filters['machine_code'];

        $query->whereHas(
            'machine',
            fn (Builder $machineQuery) =>
                $machineQuery->where(
                    'code',
                    $machineCode
                )
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyLineFilter(
        Builder $query,
        array $filters
    ): void {
        if (empty($filters['line_code'])) {
            return;
        }

        $lineCode = $filters['line_code'];

        $query->whereHas(
            'productionLine',
            fn (Builder $lineQuery) =>
                $lineQuery->where(
                    'code',
                    $lineCode
                )
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyShiftFilter(
        Builder $query,
        array $filters
    ): void {
        if (empty($filters['shift_code'])) {
            return;
        }

        $shiftCode = $filters['shift_code'];

        $query->whereHas(
            'shift',
            fn (Builder $shiftQuery) =>
                $shiftQuery->where(
                    'code',
                    $shiftCode
                )
        );
    }
}