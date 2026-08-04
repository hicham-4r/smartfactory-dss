<?php

namespace App\Repositories;

use App\Contracts\Repositories\ErpMasterDataRepositoryInterface;
use App\Models\ErpMachine;
use App\Models\ErpOperator;
use App\Models\ErpOperatorAssignment;
use App\Models\ErpProduct;
use App\Models\ErpProductFamily;
use App\Models\ErpProductionLine;
use App\Models\ErpShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentErpMasterDataRepository implements
    ErpMasterDataRepositoryInterface
{
    public function productFamilies(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpProductFamily::query()
            ->withCount('products')
            ->orderBy('code');

        $this->applyCommonFilters(
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
                    function (
                        Builder $query
                    ) use ($search): void {
                        $query
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
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
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

    public function products(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpProduct::query()
            ->with([
                'family',
                'packagingFormat',
                'productionLines',
            ])
            ->orderBy('code');

        $this->applyCommonFilters($query, $filters);

        $query->when(
            $filters['search'] ?? null,
            function (
                Builder $query,
                string $search
            ): void {
                $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('flavor', 'like', "%{$search}%")
                            ->orWhere(
                                'beverage_type',
                                'like',
                                "%{$search}%"
                            );
                    }
                );
            }
        );

        $query->when(
            $filters['family_code'] ?? null,
            function (
                Builder $query,
                string $familyCode
            ): void {
                $query->whereHas(
                    'family',
                    fn (Builder $familyQuery) =>
                        $familyQuery->where(
                            'code',
                            $familyCode
                        )
                );
            }
        );

        $query->when(
            $filters['format_code'] ?? null,
            function (
                Builder $query,
                string $formatCode
            ): void {
                $query->whereHas(
                    'packagingFormat',
                    fn (Builder $formatQuery) =>
                        $formatQuery->where(
                            'code',
                            $formatCode
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
                    'productionLines',
                    fn (Builder $lineQuery) =>
                        $lineQuery->where(
                            'code',
                            $lineCode
                        )
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function productionLines(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpProductionLine::query()
            ->with([
                'machines',
                'products',
            ])
            ->withCount([
                'machines',
                'products',
            ])
            ->orderBy('code');

        $this->applyCommonFilters($query, $filters);

        $query->when(
            $filters['status'] ?? null,
            fn (
                Builder $query,
                string $status
            ) => $query->where('status', $status)
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
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    }
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function machines(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpMachine::query()
            ->with('productionLines')
            ->orderBy('code');

        $this->applyCommonFilters($query, $filters);

        $query->when(
            $filters['status'] ?? null,
            fn (
                Builder $query,
                string $status
            ) => $query->where('status', $status)
        );

        $query->when(
            $filters['criticality'] ?? null,
            fn (
                Builder $query,
                string $criticality
            ) => $query->where(
                'criticality',
                $criticality
            )
        );

        $query->when(
            $filters['machine_type'] ?? null,
            fn (
                Builder $query,
                string $machineType
            ) => $query->where(
                'machine_type',
                $machineType
            )
        );

        $query->when(
            $filters['line_code'] ?? null,
            function (
                Builder $query,
                string $lineCode
            ): void {
                $query->whereHas(
                    'productionLines',
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
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere(
                                'machine_type',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'manufacturer',
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

    public function shifts(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpShift::query()
            ->withCount('operatorAssignments')
            ->orderBy('start_time');

        $this->applyCommonFilters($query, $filters);

        $query->when(
            $filters['status'] ?? null,
            fn (
                Builder $query,
                string $status
            ) => $query->where('status', $status)
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
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    }
                );
            }
        );

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function operators(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpOperator::query()
            ->with([
                'assignments.productionLine',
                'assignments.shift',
            ])
            ->orderBy('employee_code');

        $this->applyCommonFilters($query, $filters);

        $query->when(
            $filters['status'] ?? null,
            fn (
                Builder $query,
                string $status
            ) => $query->where('status', $status)
        );

        $query->when(
            $filters['line_code'] ?? null,
            function (
                Builder $query,
                string $lineCode
            ): void {
                $query->whereHas(
                    'assignments.productionLine',
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
                    'assignments.shift',
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
                                'employee_code',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'first_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'last_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
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

    public function operatorAssignments(
        array $filters
    ): LengthAwarePaginator {
        $query = ErpOperatorAssignment::query()
            ->with([
                'operator',
                'productionLine',
                'shift',
            ])
            ->orderBy('assigned_from')
            ->orderBy('id');

        $this->applyCommonFilters(
            $query,
            $filters
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
                    function (
                        Builder $query
                    ) use ($search): void {
                        $query
                            ->where(
                                'role_on_line',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'operator',
                                function (
                                    Builder $operatorQuery
                                ) use ($search): void {
                                    $operatorQuery
                                        ->where(
                                            'employee_code',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'first_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'last_name',
                                            'like',
                                            "%{$search}%"
                                        );
                                }
                            )
                            ->orWhereHas(
                                'productionLine',
                                function (
                                    Builder $lineQuery
                                ) use ($search): void {
                                    $lineQuery
                                        ->where(
                                            'code',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        );
                                }
                            )
                            ->orWhereHas(
                                'shift',
                                function (
                                    Builder $shiftQuery
                                ) use ($search): void {
                                    $shiftQuery
                                        ->where(
                                            'code',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        );
                                }
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
     * Apply filters shared by all ERP master-data entities.
     *
     * @param array<string, mixed> $filters
     */
    private function applyCommonFilters(
        Builder $query,
        array $filters
    ): void {
        if (array_key_exists('active', $filters)) {
            $query->where(
                'is_active',
                $filters['active']
            );
        }

        if (!empty($filters['updated_since'])) {
            $query->where(
                'updated_at',
                '>=',
                $filters['updated_since']
            );
        }
    }
}
