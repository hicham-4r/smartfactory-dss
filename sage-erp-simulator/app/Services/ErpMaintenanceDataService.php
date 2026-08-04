<?php

namespace App\Services;

use App\Contracts\Repositories\ErpMaintenanceDataRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ErpMaintenanceDataService
{
    public function __construct(
        private readonly ErpMaintenanceDataRepositoryInterface $repository
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function downtimeEvents(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->downtimeEvents($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function machineStatusEvents(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->machineStatusEvents(
            $filters
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function maintenanceHistory(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->maintenanceHistory(
            $filters
        );
    }
}