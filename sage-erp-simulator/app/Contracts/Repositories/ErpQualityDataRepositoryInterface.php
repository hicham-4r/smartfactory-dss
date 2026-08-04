<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ErpQualityDataRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function qualityInspections(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function qualityTestResults(
        array $filters
    ): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function finishedLotReleases(
        array $filters
    ): LengthAwarePaginator;

    /**
     * Failed quality inspections exposed as canonical ERP
     * nonconformity records.
     *
     * @param array<string, mixed> $filters
     */
    public function nonconformities(
        array $filters
    ): LengthAwarePaginator;
}
