<?php

namespace App\Providers;

use App\Contracts\AI\Datasets\DatasetSnapshotRepositoryInterface;
use App\Repositories\Eloquent\EloquentDatasetSnapshotRepository;
use Illuminate\Support\ServiceProvider;

final class AiDatasetServiceProvider extends
    ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DatasetSnapshotRepositoryInterface::class,
            EloquentDatasetSnapshotRepository::class
        );
    }
}
