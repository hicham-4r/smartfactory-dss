<?php

namespace App\Repositories\Eloquent;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Repositories\Contracts\MasterDataBrowseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentMasterDataBrowseRepository implements
    MasterDataBrowseRepositoryInterface
{
    /**
     * @return array<string, int>
     */
    public function overviewCounts(): array
    {
        return [
            'product_families' =>
                ProductFamily::query()->count(),

            'products' =>
                Product::query()->count(),

            'production_lines' =>
                ProductionLine::query()->count(),

            'machines' =>
                Machine::query()->count(),

            'shifts' =>
                Shift::query()->count(),

            'operators' =>
                Operator::query()->count(),

            'operator_assignments' =>
                OperatorAssignment::query()->count(),
        ];
    }

    public function products(
        array $filters
    ): LengthAwarePaginator {
        $query = Product::query()
            ->with('productFamily');

        $this->applyStatusFilter(
            $query,
            $filters
        );

        $this->applySourceFilter(
            $query,
            $filters
        );

        if (
            isset($filters['product_family_id'])
            && $filters['product_family_id'] !== null
        ) {
            $query->where(
                'product_family_id',
                $filters['product_family_id']
            );
        }

        if ($search = $this->searchTerm($filters)) {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $query) use (
                    $like
                ): void {
                    $query
                        ->where('code', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere(
                            'package_format',
                            'like',
                            $like
                        )
                        ->orWhereHas(
                            'productFamily',
                            function (
                                Builder $familyQuery
                            ) use ($like): void {
                                $familyQuery
                                    ->where(
                                        'code',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'name',
                                        'like',
                                        $like
                                    );
                            }
                        );
                }
            );
        }

        return $query
            ->orderBy('name')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();
    }

    public function productionLines(
        array $filters
    ): LengthAwarePaginator {
        $query = ProductionLine::query()
            ->withCount('machines');

        $this->applyStatusFilter(
            $query,
            $filters
        );

        $this->applySourceFilter(
            $query,
            $filters
        );

        if ($search = $this->searchTerm($filters)) {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $query) use (
                    $like
                ): void {
                    $query
                        ->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere(
                            'plant_area',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'description',
                            'like',
                            $like
                        );
                }
            );
        }

        return $query
            ->orderBy('code')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();
    }

    public function machines(
        array $filters
    ): LengthAwarePaginator {
        $query = Machine::query()
            ->with('productionLine');

        $this->applyStatusFilter(
            $query,
            $filters
        );

        $this->applySourceFilter(
            $query,
            $filters
        );

        if (
            isset($filters['production_line_id'])
            && $filters['production_line_id'] !== null
        ) {
            $query->where(
                'production_line_id',
                $filters['production_line_id']
            );
        }

        if ($search = $this->searchTerm($filters)) {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $query) use (
                    $like
                ): void {
                    $query
                        ->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere(
                            'machine_type',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'manufacturer',
                            'like',
                            $like
                        )
                        ->orWhere('model', 'like', $like)
                        ->orWhere(
                            'serial_number',
                            'like',
                            $like
                        )
                        ->orWhereHas(
                            'productionLine',
                            function (
                                Builder $lineQuery
                            ) use ($like): void {
                                $lineQuery
                                    ->where(
                                        'code',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'name',
                                        'like',
                                        $like
                                    );
                            }
                        );
                }
            );
        }

        return $query
            ->orderBy('production_line_id')
            ->orderBy('sequence_number')
            ->orderBy('name')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();
    }

    public function shifts(
        array $filters
    ): LengthAwarePaginator {
        $query = Shift::query()
            ->withCount('operatorAssignments');

        $this->applyStatusFilter(
            $query,
            $filters
        );

        $this->applySourceFilter(
            $query,
            $filters
        );

        if ($search = $this->searchTerm($filters)) {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $query) use (
                    $like
                ): void {
                    $query
                        ->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                }
            );
        }

        return $query
            ->orderBy('starts_at')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();
    }

    public function operators(
        array $filters
    ): LengthAwarePaginator {
        $query = Operator::query()
            ->withCount('assignments');

        $this->applyStatusFilter(
            $query,
            $filters
        );

        $this->applySourceFilter(
            $query,
            $filters
        );

        if ($search = $this->searchTerm($filters)) {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $query) use (
                    $like
                ): void {
                    $query
                        ->where(
                            'employee_code',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'first_name',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'last_name',
                            'like',
                            $like
                        );
                }
            );
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();
    }

    public function operatorAssignments(
        array $filters
    ): LengthAwarePaginator {
        $query = OperatorAssignment::query()
            ->with([
                'operator',
                'productionLine',
                'shift',
            ]);

        $this->applyStatusFilter(
            $query,
            $filters
        );

        $this->applySourceFilter(
            $query,
            $filters
        );

        if (
            isset($filters['production_line_id'])
            && $filters['production_line_id'] !== null
        ) {
            $query->where(
                'production_line_id',
                $filters['production_line_id']
            );
        }

        if (
            isset($filters['shift_id'])
            && $filters['shift_id'] !== null
        ) {
            $query->where(
                'shift_id',
                $filters['shift_id']
            );
        }

        if ($search = $this->searchTerm($filters)) {
            $like = '%'.$search.'%';

            $query->where(
                function (Builder $query) use (
                    $like
                ): void {
                    $query
                        ->whereHas(
                            'operator',
                            function (
                                Builder $operatorQuery
                            ) use ($like): void {
                                $operatorQuery
                                    ->where(
                                        'employee_code',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'first_name',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'last_name',
                                        'like',
                                        $like
                                    );
                            }
                        )
                        ->orWhereHas(
                            'productionLine',
                            function (
                                Builder $lineQuery
                            ) use ($like): void {
                                $lineQuery
                                    ->where(
                                        'code',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'name',
                                        'like',
                                        $like
                                    );
                            }
                        )
                        ->orWhereHas(
                            'shift',
                            function (
                                Builder $shiftQuery
                            ) use ($like): void {
                                $shiftQuery
                                    ->where(
                                        'code',
                                        'like',
                                        $like
                                    )
                                    ->orWhere(
                                        'name',
                                        'like',
                                        $like
                                    );
                            }
                        );
                }
            );
        }

        return $query
            ->orderByDesc('is_active')
            ->orderByDesc('is_primary')
            ->orderBy('production_line_id')
            ->orderBy('shift_id')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();
    }

    public function productFamilyOptions(): Collection
    {
        return ProductFamily::query()
            ->active()
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);
    }

    public function productionLineOptions(): Collection
    {
        return ProductionLine::query()
            ->active()
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
            ]);
    }

    public function shiftOptions(): Collection
    {
        return Shift::query()
            ->active()
            ->orderBy('starts_at')
            ->get([
                'id',
                'code',
                'name',
            ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyStatusFilter(
        Builder $query,
        array $filters
    ): void {
        $status = $filters['status'] ?? 'all';

        if ($status === 'active') {
            $query->where('is_active', true);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applySourceFilter(
        Builder $query,
        array $filters
    ): void {
        $sourceSystem =
            $filters['source_system'] ?? null;

        if (
            is_string($sourceSystem)
            && $sourceSystem !== ''
        ) {
            $query->where(
                'source_system',
                $sourceSystem
            );
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function searchTerm(
        array $filters
    ): ?string {
        $search = $filters['q'] ?? null;

        if (! is_string($search)) {
            return null;
        }

        $search = trim($search);

        return $search === ''
            ? null
            : $search;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function perPage(
        array $filters
    ): int {
        $perPage = (int) (
            $filters['per_page'] ?? 15
        );

        return in_array(
            $perPage,
            [15, 25, 50],
            true
        )
            ? $perPage
            : 15;
    }
}