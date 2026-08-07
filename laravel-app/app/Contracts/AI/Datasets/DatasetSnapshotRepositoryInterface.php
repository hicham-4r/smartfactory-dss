<?php

namespace App\Contracts\AI\Datasets;

use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\Enums\AI\DatasetType;
use Illuminate\Support\LazyCollection;

interface DatasetSnapshotRepositoryInterface
{
    /**
     * Return sanitized rows in deterministic order.
     *
     * @return LazyCollection<int, array<string, int|string|null>>
     */
    public function rows(
        DatasetType $dataset,
        DatasetSnapshotRequest $request
    ): LazyCollection;
}
