<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ErpMasterDataRepositoryInterface
{
    /**
     * Retrieve paginated simulated product families.
     *
     * @param array<string, mixed> $filters
     */
    public function productFamilies(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Retrieve paginated simulated products.
     *
     * @param array<string, mixed> $filters
     */
    public function products(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Retrieve paginated simulated production lines.
     *
     * @param array<string, mixed> $filters
     */
    public function productionLines(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Retrieve paginated simulated machines.
     *
     * @param array<string, mixed> $filters
     */
    public function machines(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Retrieve paginated simulated shifts.
     *
     * @param array<string, mixed> $filters
     */
    public function shifts(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Retrieve paginated simulated operators.
     *
     * @param array<string, mixed> $filters
     */
    public function operators(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Retrieve paginated simulated operator assignments.
     *
     * @param array<string, mixed> $filters
     */
    public function operatorAssignments(
        array $filters
    ): LengthAwarePaginator;
}
