<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'sage-erp-simulator',
        'data_source' => 'simulated',
        'timestamp' => now()->toIso8601String(),
    ]);
});