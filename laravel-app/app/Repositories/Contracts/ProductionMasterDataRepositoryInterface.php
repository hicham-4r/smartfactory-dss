<?php

namespace App\Repositories\Contracts;

use DateTimeInterface;
use Illuminate\Support\Collection;

interface ProductionMasterDataRepositoryInterface
{
    /**
     * Return active product families with active products.
     *
     * @return Collection<int, \App\Models\ProductFamily>
     */
    public function activeProductFamilies(): Collection;

    /**
     * Return active products, optionally for one family.
     *
     * @return Collection<int, \App\Models\Product>
     */
    public function activeProducts(
        ?int $productFamilyId = null
    ): Collection;

    /**
     * Return active lines with active machines.
     *
     * @return Collection<int, \App\Models\ProductionLine>
     */
    public function activeProductionLines(): Collection;

    /**
     * Return active machines, optionally for one line.
     *
     * @return Collection<int, \App\Models\Machine>
     */
    public function activeMachines(
        ?int $productionLineId = null
    ): Collection;

    /**
     * Return active shifts.
     *
     * @return Collection<int, \App\Models\Shift>
     */
    public function activeShifts(): Collection;

    /**
     * Return active operators and their authentication accounts.
     *
     * @return Collection<int, \App\Models\Operator>
     */
    public function activeOperators(): Collection;

    /**
     * Return effective operator assignments.
     *
     * @return Collection<int, \App\Models\OperatorAssignment>
     */
    public function currentOperatorAssignments(
        ?DateTimeInterface $date = null
    ): Collection;
}