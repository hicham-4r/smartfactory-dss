<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics timezone and range controls
    |--------------------------------------------------------------------------
    */

    'default_timezone' => env(
        'ANALYTICS_TIMEZONE',
        'Africa/Casablanca'
    ),

    'allowed_timezones' => [
        'Africa/Casablanca',
        'UTC',
    ],

    'maximum_range_days' => 366,

    /*
    |--------------------------------------------------------------------------
    | Records eligible for deterministic KPI calculations
    |--------------------------------------------------------------------------
    |
    | Manual records are marked not_applicable. Imported and skipped records
    | represent successfully synchronized rows. Pending and failed records are
    | excluded so incomplete imports cannot influence business indicators.
    |
    */

    'included_import_statuses' => [
        'not_applicable',
        'imported',
        'skipped',
    ],
];
