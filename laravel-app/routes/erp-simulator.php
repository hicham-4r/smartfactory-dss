<?php

use App\Enums\ERP\ErpResource;
use App\Http\Controllers\API\ERP\ErpSimulatorController;
use App\Http\Middleware\VerifyErpSimulatorToken;
use Illuminate\Support\Facades\Route;

$requestsPerMinute = max(
    10,
    min(
        1000,
        (int) config(
            'erp.simulator.rate_limit_per_minute',
            120
        )
    )
);

Route::prefix('erp/v1')
    ->name('erp.simulator.')
    ->middleware([
        VerifyErpSimulatorToken::class,

        'throttle:'
        .$requestsPerMinute
        .',1',
    ])
    ->group(function (): void {
        Route::get(
            '/health',
            [
                ErpSimulatorController::class,
                'health',
            ]
        )->name('health');

        foreach (
            ErpResource::cases()
            as $resource
        ) {
            $path = str_replace(
                '_',
                '-',
                $resource->value
            );

            Route::get(
                '/'.$path,
                [
                    ErpSimulatorController::class,
                    'index',
                ]
            )
                ->defaults(
                    'erpResource',
                    $resource->value
                )
                ->name(
                    'resources.'
                    .$resource->value
                );
        }
    });