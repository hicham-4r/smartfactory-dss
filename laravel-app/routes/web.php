<?php

use App\Enums\PermissionName;
use App\Http\Controllers\Admin\AdministratorOperationsController;
use App\Http\Controllers\Admin\ErpMonitoringController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\OperatorAdministrationController;
use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\MandatoryPasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Security\TwoFactorSecurityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/security/password/change-required',
        [
            MandatoryPasswordChangeController::class,
            'edit',
        ]
    )->name('security.password.required');

    Route::put(
        '/security/password/change-required',
        [
            MandatoryPasswordChangeController::class,
            'update',
        ]
    )->name('security.password.required.update');

    Route::get(
        '/security/two-factor',
        [
            TwoFactorSecurityController::class,
            'show',
        ]
    )
        ->middleware([
            'password.changed',
            'can:'
            .PermissionName::ViewAdministratorDashboard
                ->value,
        ])
        ->name('security.two-factor.show');

    Route::post(
        '/security/two-factor/recovery-codes/reveal',
        [
            TwoFactorSecurityController::class,
            'revealRecoveryCodes',
        ]
    )
        ->middleware([
            'password.changed',
            'can:'
            .PermissionName::ViewAdministratorDashboard
                ->value,
            'password.confirm',
        ])
        ->name(
            'security.two-factor.recovery-codes.reveal'
        );

    Route::get(
        '/dashboard',
        [
            DashboardController::class,
            'index',
        ]
    )
        ->middleware([
            'password.changed',
            'administrator.2fa',
        ])
        ->name('dashboard');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware([
            'password.changed',
            'administrator.2fa',
        ])
        ->group(function (): void {
            Route::get(
                '/',
                AdministratorOperationsController::class
            )
                ->middleware(
                    'can:'
                    .PermissionName::ViewAdministratorDashboard
                        ->value
                )
                ->name('dashboard');

            /*
             * User administration.
             */
            Route::get(
                '/users',
                [
                    UserManagementController::class,
                    'index',
                ]
            )
                ->middleware(
                    'can:'
                    .PermissionName::ViewUsers->value
                )
                ->name('users.index');

            Route::get(
                '/users/create',
                [
                    UserManagementController::class,
                    'create',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::CreateUsers->value,
                    'password.confirm',
                ])
                ->name('users.create');

            Route::post(
                '/users',
                [
                    UserManagementController::class,
                    'store',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::CreateUsers->value,
                    'password.confirm',
                ])
                ->name('users.store');

            Route::patch(
                '/users/{user}/activate',
                [
                    UserManagementController::class,
                    'activate',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::ActivateUsers->value,
                    'password.confirm',
                ])
                ->name('users.activate');

            Route::patch(
                '/users/{user}/deactivate',
                [
                    UserManagementController::class,
                    'deactivate',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::DeactivateUsers->value,
                    'password.confirm',
                ])
                ->name('users.deactivate');

            Route::post(
                '/users/{user}/reset-password',
                [
                    UserManagementController::class,
                    'resetPassword',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::ResetUserPasswords
                        ->value,
                    'password.confirm',
                ])
                ->name('users.reset-password');

            /*
             * Operator account linkage and assignment administration.
             */
            Route::prefix('operator-administration')
                ->name('operator-administration.')
                ->middleware(
                    'can:'
                    .PermissionName::ViewAdministratorDashboard
                        ->value
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            OperatorAdministrationController::class,
                            'index',
                        ]
                    )->name('index');

                    Route::get(
                        '/{operator}',
                        [
                            OperatorAdministrationController::class,
                            'show',
                        ]
                    )
                        ->whereNumber('operator')
                        ->middleware(
                            'password.confirm'
                        )
                        ->name('show');

                    Route::post(
                        '/{operator}/account',
                        [
                            OperatorAdministrationController::class,
                            'linkAccount',
                        ]
                    )
                        ->whereNumber('operator')
                        ->middleware([
                            'can:'
                            .PermissionName::UpdateUsers
                                ->value,
                            'password.confirm',
                        ])
                        ->name('account.link');

                    Route::delete(
                        '/{operator}/account',
                        [
                            OperatorAdministrationController::class,
                            'unlinkAccount',
                        ]
                    )
                        ->whereNumber('operator')
                        ->middleware([
                            'can:'
                            .PermissionName::UpdateUsers
                                ->value,
                            'password.confirm',
                        ])
                        ->name('account.unlink');

                    Route::post(
                        '/{operator}/assignments',
                        [
                            OperatorAdministrationController::class,
                            'storeAssignment',
                        ]
                    )
                        ->whereNumber('operator')
                        ->middleware([
                            'can:'
                            .PermissionName::UpdateUsers
                                ->value,
                            'password.confirm',
                        ])
                        ->name('assignments.store');

                    Route::put(
                        '/{operator}/assignments/{operatorAssignment}',
                        [
                            OperatorAdministrationController::class,
                            'updateAssignment',
                        ]
                    )
                        ->whereNumber('operator')
                        ->whereNumber(
                            'operatorAssignment'
                        )
                        ->middleware([
                            'can:'
                            .PermissionName::UpdateUsers
                                ->value,
                            'password.confirm',
                        ])
                        ->name('assignments.update');

                    Route::patch(
                        '/{operator}/assignments/{operatorAssignment}/end',
                        [
                            OperatorAdministrationController::class,
                            'endAssignment',
                        ]
                    )
                        ->whereNumber('operator')
                        ->whereNumber(
                            'operatorAssignment'
                        )
                        ->middleware([
                            'can:'
                            .PermissionName::UpdateUsers
                                ->value,
                            'password.confirm',
                        ])
                        ->name('assignments.end');
                });

            /*
             * Roles and permissions.
             */
            Route::get(
                '/roles',
                [
                    RoleManagementController::class,
                    'index',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::ViewRoles->value,
                    'can:'
                    .PermissionName::ViewPermissions
                        ->value,
                ])
                ->name('roles.index');

            Route::get(
                '/roles/{role}/edit',
                [
                    RoleManagementController::class,
                    'edit',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::ManageRoles->value,
                    'can:'
                    .PermissionName::ManagePermissions
                        ->value,
                ])
                ->name('roles.edit');

            Route::put(
                '/roles/{role}/permissions',
                [
                    RoleManagementController::class,
                    'update',
                ]
            )
                ->middleware([
                    'can:'
                    .PermissionName::ManageRoles->value,
                    'can:'
                    .PermissionName::ManagePermissions
                        ->value,
                    'password.confirm',
                ])
                ->name(
                    'roles.permissions.update'
                );

            /*
             * Read-only ERP synchronization monitoring.
             */
            Route::prefix('erp-monitoring')
                ->name('erp-monitoring.')
                ->middleware([
                    'can:'
                    .PermissionName::ViewSynchronizationLogs
                        ->value,

                    'can:'
                    .PermissionName::ViewSystemHealth
                        ->value,
                ])
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            ErpMonitoringController::class,
                            'index',
                        ]
                    )->name('index');

                    Route::get(
                        '/runs/{erpSyncRun}',
                        [
                            ErpMonitoringController::class,
                            'show',
                        ]
                    )
                        ->whereNumber(
                            'erpSyncRun'
                        )
                        ->name('runs.show');
                });

            /*
             * Read-only production master data.
             */
            Route::prefix('master-data')
                ->name('master-data.')
                ->middleware(
                    'can:'
                    .PermissionName::ViewAdministratorDashboard
                        ->value
                )
                ->group(function (): void {
                    Route::get(
                        '/',
                        [
                            MasterDataController::class,
                            'index',
                        ]
                    )->name('index');

                    Route::get(
                        '/products',
                        [
                            MasterDataController::class,
                            'products',
                        ]
                    )->name('products');

                    Route::get(
                        '/production-lines',
                        [
                            MasterDataController::class,
                            'productionLines',
                        ]
                    )->name('production-lines');

                    Route::get(
                        '/machines',
                        [
                            MasterDataController::class,
                            'machines',
                        ]
                    )->name('machines');

                    Route::get(
                        '/shifts',
                        [
                            MasterDataController::class,
                            'shifts',
                        ]
                    )->name('shifts');

                    Route::get(
                        '/operators',
                        [
                            MasterDataController::class,
                            'operators',
                        ]
                    )->name('operators');

                    Route::get(
                        '/operator-assignments',
                        [
                            MasterDataController::class,
                            'assignments',
                        ]
                    )->name('assignments');
                });
        });
});

require __DIR__.'/reports.php';

require __DIR__.'/analytics.php';

require __DIR__.'/ai-insights.php';

require __DIR__.'/ai-insights-automatic.php';

require __DIR__.'/production.php';

require __DIR__.'/erp-manual-sync.php';

require __DIR__.'/notifications.php';
