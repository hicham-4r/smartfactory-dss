<?php

use App\Http\Controllers\Internal\NativeMetricsController;
use App\Http\Controllers\Api\ErpMaintenanceDataController;
use App\Http\Controllers\Api\ErpMasterDataController;
use App\Http\Controllers\Api\ErpOperationalDataController;
use App\Http\Controllers\Api\ErpQualityDataController;
use Illuminate\Support\Facades\Route;


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

/*
|--------------------------------------------------------------------------
| Public simulator health endpoint
|--------------------------------------------------------------------------
|
| This route is public. It is not protected by the ERP token and is not
| affected by data-quality or artificial-failure simulation.
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'sage-erp-simulator',
        'data_source' => 'simulated',
        'api_version' => '1.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected simulated ERP endpoints
|--------------------------------------------------------------------------
|
| Middleware order:
|
| 1. Validate the X-ERP-Token.
| 2. Apply the standard request-rate limit.
| 3. Optionally simulate an artificial API failure.
| 4. Optionally simulate missing or duplicate response data.
| 5. Execute the controller.
|
*/

Route::middleware([
    'erp.token',
    'throttle:120,1',
    'erp.failure-simulation',
    'erp.data-quality',
])->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Master-data API
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/product-families',
        [
            ErpMasterDataController::class,
            'productFamilies',
        ]
    );

    Route::get(
        '/products',
        [
            ErpMasterDataController::class,
            'products',
        ]
    );

    Route::get(
        '/production-lines',
        [
            ErpMasterDataController::class,
            'productionLines',
        ]
    );

    Route::get(
        '/machines',
        [
            ErpMasterDataController::class,
            'machines',
        ]
    );

    Route::get(
        '/shifts',
        [
            ErpMasterDataController::class,
            'shifts',
        ]
    );

    Route::get(
        '/operators',
        [
            ErpMasterDataController::class,
            'operators',
        ]
    );

    Route::get(
        '/operator-assignments',
        [
            ErpMasterDataController::class,
            'operatorAssignments',
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Production operational API
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/production-orders',
        [
            ErpOperationalDataController::class,
            'productionOrders',
        ]
    );

    Route::get(
        '/production-batches',
        [
            ErpOperationalDataController::class,
            'productionBatches',
        ]
    );

    Route::get(
        '/production-records',
        [
            ErpOperationalDataController::class,
            'productionRecords',
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Maintenance and downtime API
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/downtime-events',
        [
            ErpMaintenanceDataController::class,
            'downtimeEvents',
        ]
    );

    Route::get(
        '/machine-status-events',
        [
            ErpMaintenanceDataController::class,
            'machineStatusEvents',
        ]
    );

    Route::get(
        '/maintenance-history',
        [
            ErpMaintenanceDataController::class,
            'maintenanceHistory',
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Quality and finished-lot API
    |--------------------------------------------------------------------------
    */

    /*
     * Canonical endpoints consumed by SmartFactory DSS.
     */
    Route::get(
        '/finished-lots',
        [
            ErpQualityDataController::class,
            'finishedLots',
        ]
    );

    Route::get(
        '/inspections',
        [
            ErpQualityDataController::class,
            'inspections',
        ]
    );

    Route::get(
        '/nonconformities',
        [
            ErpQualityDataController::class,
            'nonconformities',
        ]
    );

    /*
     * Detailed simulator endpoints retained for backward compatibility.
     */
    Route::get(
        '/quality-inspections',
        [
            ErpQualityDataController::class,
            'qualityInspections',
        ]
    );

    Route::get(
        '/quality-test-results',
        [
            ErpQualityDataController::class,
            'qualityTestResults',
        ]
    );

    Route::get(
        '/finished-lot-releases',
        [
            ErpQualityDataController::class,
            'finishedLotReleases',
        ]
    );
});
