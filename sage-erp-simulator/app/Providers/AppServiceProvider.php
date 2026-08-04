<?php

namespace App\Providers;

use App\Contracts\Repositories\ErpMaintenanceDataRepositoryInterface;
use App\Contracts\Repositories\ErpMasterDataRepositoryInterface;
use App\Contracts\Repositories\ErpOperationalDataRepositoryInterface;
use App\Contracts\Repositories\ErpQualityDataRepositoryInterface;
use App\Repositories\EloquentErpMaintenanceDataRepository;
use App\Repositories\EloquentErpMasterDataRepository;
use App\Repositories\EloquentErpOperationalDataRepository;
use App\Repositories\EloquentErpQualityDataRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ErpMasterDataRepositoryInterface::class,
            EloquentErpMasterDataRepository::class
        );

        $this->app->bind(
            ErpOperationalDataRepositoryInterface::class,
            EloquentErpOperationalDataRepository::class
        );

        $this->app->bind(
            ErpMaintenanceDataRepositoryInterface::class,
            EloquentErpMaintenanceDataRepository::class
        );

        $this->app->bind(
            ErpQualityDataRepositoryInterface::class,
            EloquentErpQualityDataRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}