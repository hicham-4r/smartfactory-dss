<?php

use App\Enums\PermissionName;
use App\Http\Controllers\Admin\ManualErpSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'password.changed',
    'administrator.2fa',

    'can:'
    .PermissionName
        ::ViewSynchronizationLogs
        ->value,

    'can:'
    .PermissionName
        ::ViewSystemHealth
        ->value,
])
    ->prefix(
        'admin/erp-monitoring'
    )
    ->name(
        'admin.erp-monitoring.'
    )
    ->group(function (): void {
        Route::post(
            '/synchronize',
            ManualErpSyncController::class
        )
            ->middleware([
                'can:'
                .PermissionName
                    ::RunManualSynchronization
                    ->value,

                'password.confirm',
            ])
            ->name(
                'synchronize'
            );
    });
