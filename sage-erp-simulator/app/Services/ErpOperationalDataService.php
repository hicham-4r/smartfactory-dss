<?php

namespace App\Services;

use App\Contracts\Repositories\ErpOperationalDataRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ErpOperationalDataService
{
    public function __construct(
        private readonly ErpOperationalDataRepositoryInterface $repository
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function productionOrders(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->productionOrders($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function productionBatches(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->productionBatches($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function productionRecords(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->productionRecords($filters);
    }
}