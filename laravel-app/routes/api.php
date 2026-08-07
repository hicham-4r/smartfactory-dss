<?php

use App\Http\Controllers\Internal\NativeMetricsController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/erp-simulator.php';

/*
|--------------------------------------------------------------------------
| Internal Prometheus metrics
|--------------------------------------------------------------------------
|
| Public NGINX listeners deny this path. A dedicated ClusterIP-only NGINX
| listener exposes it solely to Prometheus through NetworkPolicy.
|
*/
Route::get('/metrics', NativeMetricsController::class)
    ->name('native-metrics');
