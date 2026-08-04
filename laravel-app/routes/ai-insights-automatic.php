<?php

use App\Http\Controllers\AI\AiInsightsController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'password.changed',
])
    ->prefix('ai-insights/automatic')
    ->name('ai-insights.automatic.')
    ->group(function (): void {
        Route::redirect(
            '/production/forecast',
            '/ai-insights'
        );

        Route::redirect(
            '/production/anomaly',
            '/ai-insights'
        );

        Route::redirect(
            '/maintenance/risk',
            '/ai-insights'
        );

        Route::post(
            '/production/forecast',
            [
                AiInsightsController::class,
                'automaticForecast',
            ]
        )->name('production.forecast');

        Route::post(
            '/production/anomaly',
            [
                AiInsightsController::class,
                'automaticAnomaly',
            ]
        )->name('production.anomaly');

        Route::post(
            '/maintenance/risk',
            [
                AiInsightsController::class,
                'automaticMaintenanceRisk',
            ]
        )->name('maintenance.risk');
    });
