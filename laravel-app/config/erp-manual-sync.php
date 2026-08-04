<?php

return [
    /*
     * Manual synchronization jobs are isolated on their own queue so
     * they cannot delay unrelated application jobs.
     */
    'queue' => env(
        'ERP_MANUAL_SYNC_QUEUE',
        'erp-sync'
    ),

    /*
     * An administrator may enqueue only one manual synchronization
     * request during this window.
     */
    'cooldown_seconds' => (int) env(
        'ERP_MANUAL_SYNC_COOLDOWN_SECONDS',
        300
    ),

    'per_page' => (int) env(
        'ERP_MANUAL_SYNC_PER_PAGE',
        100
    ),

    'max_pages' => (int) env(
        'ERP_MANUAL_SYNC_MAX_PAGES',
        100
    ),

    /*
     * The job shares the same distributed lock as the scheduled cycle.
     */
    'lock_key' => env(
        'ERP_MANUAL_SYNC_LOCK_KEY',
        env(
            'ERP_SYNC_AUTOMATION_LOCK_KEY',
            'smartfactory:erp:incremental-sync-cycle'
        )
    ),

    'lock_seconds' => (int) env(
        'ERP_MANUAL_SYNC_LOCK_SECONDS',
        7200
    ),

    'retry_after_seconds' => (int) env(
        'ERP_MANUAL_SYNC_RETRY_AFTER_SECONDS',
        60
    ),
];
