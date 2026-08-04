<?php

use App\Http\Controllers\Notifications\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'password.changed',
    'administrator.2fa',
])
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                NotificationCenterController::class,
                'index',
            ]
        )->name('index');

        Route::patch(
            '/read-all',
            [
                NotificationCenterController::class,
                'markAllRead',
            ]
        )->name('read-all');

        Route::patch(
            '/{notification}/read',
            [
                NotificationCenterController::class,
                'markRead',
            ]
        )
            ->whereUuid('notification')
            ->name('read');
    });
