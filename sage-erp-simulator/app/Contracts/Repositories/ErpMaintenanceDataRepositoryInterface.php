<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ErpMaintenanceDataRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function downtimeEvents(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function machineStatusEvents(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function maintenanceHistory(
        array $filters
    ): LengthAwarePaginator;
}