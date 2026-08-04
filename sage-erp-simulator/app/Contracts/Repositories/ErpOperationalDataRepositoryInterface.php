<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ErpOperationalDataRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function productionOrders(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function productionBatches(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function productionRecords(
        array $filters
    ): LengthAwarePaginator;
}