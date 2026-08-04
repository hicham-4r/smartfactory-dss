<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountCanAuthenticate;
use App\Http\Middleware\EnsureAdministratorHasTwoFactorAuthentication;
use App\Http\Middleware\EnsurePasswordChanged;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {
            $middleware->append(
                AssignRequestId::class
            );

            $middleware->append(
                AddSecurityHeaders::class
            );

            $middleware->appendToGroup(
                'web',
                EnsureAccountCanAuthenticate::class
            );

            $middleware->alias([
                'password.changed' =>
                    EnsurePasswordChanged::class,

                'administrator.2fa' =>
                    EnsureAdministratorHasTwoFactorAuthentication::class,
            ]);
        }
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            //
        }
    )
    ->create();