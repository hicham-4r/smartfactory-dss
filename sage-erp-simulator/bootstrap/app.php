<?php

use App\Http\Middleware\ApplySimulatedApiFailure;
use App\Http\Middleware\ApplySimulatedDataQualityScenario;
use App\Http\Middleware\VerifyErpApiToken;
use App\Http\Middleware\RecordNativeMetrics;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {
            $middleware->append(
                RecordNativeMetrics::class
            );

            $middleware->alias([
                'erp.token' =>
                    VerifyErpApiToken::class,

                'erp.failure-simulation' =>
                    ApplySimulatedApiFailure::class,

                'erp.data-quality' =>
                    ApplySimulatedDataQualityScenario::class,
            ]);
        }
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            //
        }
    )
    ->create();
