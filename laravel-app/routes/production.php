<?php

use App\Enums\PermissionName;
use App\Http\Controllers\Production\OperatorProductionController;
use App\Http\Controllers\Production\OperatorProductionEventController;
use App\Http\Controllers\Production\OperatorProductionRecordController;
use App\Http\Controllers\Production\SupervisorProductionBatchController;
use App\Http\Controllers\Production\SupervisorProductionController;
use App\Http\Controllers\Production\SupervisorProductionEventController;
use App\Http\Controllers\Production\SupervisorProductionOrderController;
use App\Http\Controllers\Production\SupervisorProductionRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operator production workflow
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'password.changed',
    'administrator.2fa',

    'can:'
    .PermissionName
        ::ViewAssignedProductionOrders
        ->value,
])
    ->prefix('production/operator')
    ->name('production.operator.')
    ->group(function (): void {
        Route::middleware(
            'throttle:120,1'
        )->group(function (): void {
            Route::get(
                '/',
                [
                    OperatorProductionController::class,
                    'index',
                ]
            )->name('index');

            Route::get(
                '/orders/{productionOrder}',
                [
                    OperatorProductionController::class,
                    'showOrder',
                ]
            )->name('orders.show');

            Route::get(
                '/batches/{productionBatch}',
                [
                    OperatorProductionController::class,
                    'showBatch',
                ]
            )->name('batches.show');

            Route::get(
                '/batches/{productionBatch}/records/create',
                [
                    OperatorProductionRecordController::class,
                    'create',
                ]
            )->name('records.create');

            Route::get(
                '/records/{productionRecord}',
                [
                    OperatorProductionRecordController::class,
                    'show',
                ]
            )->name('records.show');

            Route::get(
                '/batches/{productionBatch}/events/create',
                [
                    OperatorProductionEventController::class,
                    'create',
                ]
            )->name('events.create');

            Route::get(
                '/events/{productionEvent}',
                [
                    OperatorProductionEventController::class,
                    'show',
                ]
            )->name('events.show');
        });

        Route::middleware(
            'throttle:20,1'
        )->group(function (): void {
            Route::post(
                '/batches/{productionBatch}/records',
                [
                    OperatorProductionRecordController::class,
                    'store',
                ]
            )->name('records.store');

            Route::post(
                '/records/{productionRecord}/submit',
                [
                    OperatorProductionRecordController::class,
                    'submit',
                ]
            )->name('records.submit');

            Route::post(
                '/batches/{productionBatch}/events',
                [
                    OperatorProductionEventController::class,
                    'store',
                ]
            )->name('events.store');
        });
    });

/*
|--------------------------------------------------------------------------
| Production Supervisor workflow
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'password.changed',
    'administrator.2fa',

    'can:'
    .PermissionName
        ::ViewProductionSupervisorDashboard
        ->value,
])
    ->prefix('production/supervisor')
    ->name('production.supervisor.')
    ->group(function (): void {
        Route::middleware(
            'throttle:120,1'
        )->group(function (): void {
            Route::get(
                '/',
                [
                    SupervisorProductionController::class,
                    'index',
                ]
            )->name('index');

            Route::get(
                '/orders/create',
                [
                    SupervisorProductionOrderController::class,
                    'create',
                ]
            )->name('orders.create');

            Route::get(
                '/orders/{productionOrder}',
                [
                    SupervisorProductionController::class,
                    'showOrder',
                ]
            )->name('orders.show');

            Route::get(
                '/batches/{productionBatch}',
                [
                    SupervisorProductionController::class,
                    'showBatch',
                ]
            )->name('batches.show');

            Route::get(
                '/records/{productionRecord}',
                [
                    SupervisorProductionController::class,
                    'showRecord',
                ]
            )->name('records.show');

            Route::get(
                '/events/{productionEvent}',
                [
                    SupervisorProductionController::class,
                    'showEvent',
                ]
            )->name('events.show');
        });

        Route::middleware(
            'throttle:20,1'
        )->group(function (): void {
            Route::post(
                '/orders',
                [
                    SupervisorProductionOrderController::class,
                    'store',
                ]
            )->name('orders.store');

            Route::post(
                '/orders/{productionOrder}/transition',
                [
                    SupervisorProductionOrderController::class,
                    'transition',
                ]
            )->name('orders.transition');

            Route::post(
                '/orders/{productionOrder}/batches',
                [
                    SupervisorProductionBatchController::class,
                    'store',
                ]
            )->name('batches.store');

            Route::post(
                '/batches/{productionBatch}/transition',
                [
                    SupervisorProductionBatchController::class,
                    'transition',
                ]
            )->name('batches.transition');

            Route::post(
                '/records/{productionRecord}/decision',
                [
                    SupervisorProductionRecordController::class,
                    'decide',
                ]
            )->name('records.decide');

            Route::post(
                '/events/{productionEvent}/resolve',
                [
                    SupervisorProductionEventController::class,
                    'resolve',
                ]
            )->name('events.resolve');
        });
    });