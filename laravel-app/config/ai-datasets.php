<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared dataset root
    |--------------------------------------------------------------------------
    |
    | Native Windows development should set this to a directory on disk D.
    | Docker and Kubernetes will later point it to a mounted persistent path.
    | Application code never hardcodes an operating-system-specific path.
    |
    */

    'root' => env(
        'AI_DATASET_ROOT',
        storage_path(
            'app/private/ai-datasets'
        )
    ),

    /*
    |--------------------------------------------------------------------------
    | Snapshot controls
    |--------------------------------------------------------------------------
    */

    'default_timezone' => env(
        'AI_DATASET_TIMEZONE',
        config(
            'analytics.default_timezone',
            'Africa/Casablanca'
        )
    ),

    'default_lookback_days' => (int) env(
        'AI_DATASET_DEFAULT_LOOKBACK_DAYS',
        180
    ),

    'maximum_range_days' => (int) env(
        'AI_DATASET_MAXIMUM_RANGE_DAYS',
        366
    ),

    'maximum_rows_per_file' => (int) env(
        'AI_DATASET_MAXIMUM_ROWS_PER_FILE',
        1000000
    ),

    'maximum_bytes_per_file' => (int) env(
        'AI_DATASET_MAXIMUM_BYTES_PER_FILE',
        536870912
    ),

    'source_system' => env(
        'AI_DATASET_SOURCE_SYSTEM',
        'simulated_sage'
    ),

    'audit_enabled' => (bool) env(
        'AI_DATASET_AUDIT_ENABLED',
        true
    ),
];
