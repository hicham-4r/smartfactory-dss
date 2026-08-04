<?php

return [
    'source_system' => env(
        'ERP_MONITORING_SOURCE_SYSTEM',
        'simulated_sage'
    ),

    /* Three missed 15-minute cycles mark a checkpoint as stale. */
    'stale_after_minutes' => (int) env(
        'ERP_MONITORING_STALE_AFTER_MINUTES',
        45
    ),

    'window_hours' => (int) env(
        'ERP_MONITORING_WINDOW_HOURS',
        24
    ),

    'recent_failure_limit' => (int) env(
        'ERP_MONITORING_RECENT_FAILURE_LIMIT',
        20
    ),
];
