<?php

namespace App\Services;

use App\Contracts\Repositories\ErpMasterDataRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ErpMasterDataService
{
    public function __construct(
        private readonly ErpMasterDataRepositoryInterface $repository
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function productFamilies(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository
            ->productFamilies($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function products(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->products($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function productionLines(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->productionLines($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function machines(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->machines($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function shifts(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->shifts($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function operators(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->operators($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function operatorAssignments(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository
            ->operatorAssignments($filters);
    }
}
