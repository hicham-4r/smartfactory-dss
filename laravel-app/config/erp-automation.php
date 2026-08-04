<?php

return [
    /*
     * Scheduled synchronization is disabled by default so installation,
     * testing, and deployments cannot unexpectedly call the ERP.
     */
    'enabled' => (bool) env(
        'ERP_SYNC_AUTOMATION_ENABLED',
        false
    ),

    /*
     * Every fifteen minutes.
     */
    'cron' => env(
        'ERP_SYNC_AUTOMATION_CRON',
        '*/15 * * * *'
    ),

    'timezone' => env(
        'ERP_SYNC_AUTOMATION_TIMEZONE',
        'UTC'
    ),

    /*
     * These values apply to each ERP dependency group.
     */
    'per_page' => (int) env(
        'ERP_SYNC_AUTOMATION_PER_PAGE',
        100
    ),

    'max_pages' => (int) env(
        'ERP_SYNC_AUTOMATION_MAX_PAGES',
        100
    ),

    /*
     * The command-level lock protects the entire five-group cycle.
     */
    'lock_key' => env(
        'ERP_SYNC_AUTOMATION_LOCK_KEY',
        'smartfactory:erp:incremental-sync-cycle'
    ),

    'lock_seconds' => (int) env(
        'ERP_SYNC_AUTOMATION_LOCK_SECONDS',
        7200
    ),

    /*
     * Laravel's scheduled-event mutex uses minutes.
     */
    'schedule_mutex_minutes' => (int) env(
        'ERP_SYNC_AUTOMATION_SCHEDULE_MUTEX_MINUTES',
        120
    ),

    'output_file' => env(
        'ERP_SYNC_AUTOMATION_OUTPUT_FILE',
        storage_path(
            'logs/erp-scheduler.log'
        )
    ),
];