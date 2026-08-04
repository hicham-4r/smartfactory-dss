<?php

return [
    'source_system' => env(
        'AI_DATASET_SOURCE_SYSTEM',
        'simulated_sage'
    ),

    'included_import_statuses' => [
        'not_applicable',
        'imported',
        'skipped',
    ],

    'production_record_option_limit' => 150,

    /*
    |--------------------------------------------------------------------------
    | Safety and model-contract controls
    |--------------------------------------------------------------------------
    */

    'maximum_history_days' => 3660,
    'minimum_forecast_observations' => 2,
    'minimum_maintenance_days_observed' => 30,
];
