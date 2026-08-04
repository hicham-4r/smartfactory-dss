<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Artificial API-failure simulation
    |--------------------------------------------------------------------------
    |
    | These scenarios affect API responses only. They never modify the
    | simulated ERP database.
    |
    */

    'enabled' => env(
        'ERP_FAILURE_SIMULATION_ENABLED',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | Dedicated simulation key
    |--------------------------------------------------------------------------
    |
    | A caller must provide this value through:
    |
    | X-ERP-Failure-Key
    |
    */

    'key' => env(
        'ERP_FAILURE_SIMULATION_KEY'
    ),

    'default_probability' => (int) env(
        'ERP_FAILURE_DEFAULT_PROBABILITY',
        100
    ),

    'default_seed' => (int) env(
        'ERP_FAILURE_DEFAULT_SEED',
        20260725
    ),

    'default_retry_after_seconds' => (int) env(
        'ERP_FAILURE_DEFAULT_RETRY_AFTER',
        30
    ),

    'default_delay_ms' => (int) env(
        'ERP_FAILURE_DEFAULT_DELAY_MS',
        1500
    ),

    /*
    |--------------------------------------------------------------------------
    | Safety limit
    |--------------------------------------------------------------------------
    |
    | Prevent a request from sleeping indefinitely.
    |
    */

    'maximum_delay_ms' => (int) env(
        'ERP_FAILURE_MAXIMUM_DELAY_MS',
        5000
    ),
];