<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MasterDataBrowseRepositoryInterface
{
    /**
     * Return overview counts for the read-only dashboard.
     *
     * @return array<string, int>
     */
    public function overviewCounts(): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, \App\Models\Product>
     */
    public function products(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, \App\Models\ProductionLine>
     */
    public function productionLines(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, \App\Models\Machine>
     */
    public function machines(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, \App\Models\Shift>
     */
    public function shifts(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, \App\Models\Operator>
     */
    public function operators(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     *
     * @return LengthAwarePaginator<int, \App\Models\OperatorAssignment>
     */
    public function operatorAssignments(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @return Collection<int, \App\Models\ProductFamily>
     */
    public function productFamilyOptions(): Collection;

    /**
     * @return Collection<int, \App\Models\ProductionLine>
     */
    public function productionLineOptions(): Collection;

    /**
     * @return Collection<int, \App\Models\Shift>
     */
    public function shiftOptions(): Collection;
}