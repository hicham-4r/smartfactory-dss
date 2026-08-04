<?php

use App\Enums\PermissionName;
use App\Http\Controllers\Analytics\AnalyticsDrilldownController;
use App\Http\Controllers\Analytics\MaintenanceKpiController;
use App\Http\Controllers\Analytics\ProductionKpiController;
use App\Http\Controllers\Analytics\QualityKpiController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'password.changed',
    'administrator.2fa',
    'throttle:120,1',
])
    ->prefix('analytics')
    ->name('analytics.')
    ->group(function (): void {
        Route::get(
            '/production',
            [
                ProductionKpiController::class,
                'index',
            ]
        )
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewProductionKpis
                    ->value
            )
            ->name('production.index');

        Route::get(
            '/maintenance',
            [
                MaintenanceKpiController::class,
                'index',
            ]
        )
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewMaintenanceKpis
                    ->value
            )
            ->name('maintenance.index');


        Route::get(
            '/quality',
            [
                QualityKpiController::class,
                'index',
            ]
        )
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewProductionKpis
                    ->value
            )
            ->name('quality.index');
        Route::prefix('production')
            ->name('production.')
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewProductionKpis
                    ->value
            )
            ->group(function (): void {
                Route::get(
                    '/lines/{productionLine}',
                    [
                        AnalyticsDrilldownController::class,
                        'productionLine',
                    ]
                )
                    ->whereNumber('productionLine')
                    ->name('lines.show');

                Route::get(
                    '/shifts/{shift}',
                    [
                        AnalyticsDrilldownController::class,
                        'productionShift',
                    ]
                )
                    ->whereNumber('shift')
                    ->name('shifts.show');

                Route::get(
                    '/products/{product}',
                    [
                        AnalyticsDrilldownController::class,
                        'productionProduct',
                    ]
                )
                    ->whereNumber('product')
                    ->name('products.show');

                Route::get(
                    '/product-families/{productFamily}',
                    [
                        AnalyticsDrilldownController::class,
                        'productionProductFamily',
                    ]
                )
                    ->whereNumber('productFamily')
                    ->name('product-families.show');

                Route::get(
                    '/orders/{productionOrder}',
                    [
                        AnalyticsDrilldownController::class,
                        'productionOrder',
                    ]
                )
                    ->whereNumber('productionOrder')
                    ->name('orders.show');
            });

        Route::prefix('maintenance')
            ->name('maintenance.')
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewMaintenanceKpis
                    ->value
            )
            ->group(function (): void {
                Route::get(
                    '/machines/{machine}',
                    [
                        AnalyticsDrilldownController::class,
                        'maintenanceMachine',
                    ]
                )
                    ->whereNumber('machine')
                    ->name('machines.show');
            });

        Route::prefix('quality')
            ->name('quality.')
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewProductionKpis
                    ->value
            )
            ->group(function (): void {
                Route::get(
                    '/lines/{productionLine}',
                    [
                        AnalyticsDrilldownController::class,
                        'qualityProductionLine',
                    ]
                )
                    ->whereNumber('productionLine')
                    ->name('lines.show');

                Route::get(
                    '/products/{product}',
                    [
                        AnalyticsDrilldownController::class,
                        'qualityProduct',
                    ]
                )
                    ->whereNumber('product')
                    ->name('products.show');

                Route::get(
                    '/product-families/{productFamily}',
                    [
                        AnalyticsDrilldownController::class,
                        'qualityProductFamily',
                    ]
                )
                    ->whereNumber('productFamily')
                    ->name('product-families.show');
            });

    });
