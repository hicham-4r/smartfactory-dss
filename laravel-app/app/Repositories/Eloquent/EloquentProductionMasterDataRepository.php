<?php

namespace App\Repositories\Eloquent;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Repositories\Contracts\ProductionMasterDataRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentProductionMasterDataRepository implements
    ProductionMasterDataRepositoryInterface
{
    /**
     * Return active product families with active products.
     */
    public function activeProductFamilies(): Collection
    {
        return ProductFamily::query()
            ->active()
            ->with([
                'products' => function ($query): void {
                    $query
                        ->active()
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Return active products, optionally filtered by family.
     */
    public function activeProducts(
        ?int $productFamilyId = null
    ): Collection {
        return Product::query()
            ->active()
            ->with('productFamily')
            ->when(
                $productFamilyId !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'product_family_id',
                        $productFamilyId
                    )
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * Return active production lines with active machines.
     */
    public function activeProductionLines(): Collection
    {
        return ProductionLine::query()
            ->active()
            ->with([
                'machines' => function ($query): void {
                    $query
                        ->active()
                        ->orderBy('sequence_number')
                        ->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Return active machines, optionally filtered by production line.
     */
    public function activeMachines(
        ?int $productionLineId = null
    ): Collection {
        return Machine::query()
            ->active()
            ->with('productionLine')
            ->when(
                $productionLineId !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        'production_line_id',
                        $productionLineId
                    )
            )
            ->orderBy('production_line_id')
            ->orderBy('sequence_number')
            ->orderBy('name')
            ->get();
    }

    /**
     * Return active shifts.
     */
    public function activeShifts(): Collection
    {
        return Shift::query()
            ->active()
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Return active operators with their authentication accounts.
     */
    public function activeOperators(): Collection
    {
        return Operator::query()
            ->active()
            ->with('user')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Return effective operator assignments.
     */
    public function currentOperatorAssignments(
        ?DateTimeInterface $date = null
    ): Collection {
        return OperatorAssignment::query()
            ->current($date)
            ->with([
                'operator.user',
                'productionLine',
                'shift',
                'assignedBy',
            ])
            ->orderByDesc('is_primary')
            ->orderBy('production_line_id')
            ->orderBy('shift_id')
            ->get();
    }
}