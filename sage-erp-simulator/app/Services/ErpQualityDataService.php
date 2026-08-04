<?php

namespace App\Services;

use App\Contracts\Repositories\ErpQualityDataRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ErpQualityDataService
{
    public function __construct(
        private readonly ErpQualityDataRepositoryInterface $repository
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function qualityInspections(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->qualityInspections(
            $filters
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function qualityTestResults(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->qualityTestResults(
            $filters
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function finishedLotReleases(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->finishedLotReleases(
            $filters
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function nonconformities(
        array $filters
    ): LengthAwarePaginator {
        return $this->repository->nonconformities(
            $filters
        );
    }
}
