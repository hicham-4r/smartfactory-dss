<?php

use App\Enums\PermissionName;
use App\Http\Controllers\Reports\AiInferenceReportController;
use App\Http\Controllers\Reports\ProductionReportController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'password.changed',
    'administrator.2fa',
    'throttle:60,1',
])
    ->prefix('reports')
    ->name('reports.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                ProductionReportController::class,
                'index',
            ]
        )
            ->middleware(
                'can:'
                .PermissionName
                    ::ViewProductionKpis
                    ->value
            )
            ->name('index');


        Route::get(
            '/ai/{token}/export/{format}',
            [
                AiInferenceReportController::class,
                'export',
            ],
        )
            ->whereUuid('token')
            ->where('format', 'csv|xlsx|pdf')
            ->name('ai.export');

        Route::get(
            '/production/export/{format}',
            [
                ProductionReportController::class,
                'export',
            ]
        )
            ->middleware(
                'can:'
                .PermissionName
                    ::ExportProductionReports
                    ->value
            )
            ->where(
                'format',
                'csv|xlsx|pdf'
            )
            ->name('production.export');
    });
