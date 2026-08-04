<?php

use App\Http\Controllers\AI\AiInsightsController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai-insights')
    ->name('ai-insights.')
    ->middleware([
        'auth',
        'password.changed',
        'administrator.2fa',
    ])
    ->group(function (): void {
        Route::get('/', [AiInsightsController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('index');


        Route::post(
            '/explanations',
            [AiInsightsController::class, 'generateExplanation'],
        )
            ->middleware('throttle:6,1')
            ->name('explanations.generate');

        Route::post(
            '/production/forecast',
            [AiInsightsController::class, 'forecast'],
        )
            ->middleware('throttle:30,1')
            ->name('production.forecast');

        Route::post(
            '/production/anomaly',
            [AiInsightsController::class, 'anomaly'],
        )
            ->middleware('throttle:30,1')
            ->name('production.anomaly');

        Route::post(
            '/maintenance/risk',
            [AiInsightsController::class, 'maintenanceRisk'],
        )
            ->middleware('throttle:30,1')
            ->name('maintenance.risk');
    });
