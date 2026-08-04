<?php

use Illuminate\Support\Facades\Schedule;

if (
    config(
        'erp-automation.enabled',
        false
    )
) {
    Schedule::command(
        'erp:sync:cycle'
    )
        ->name(
            'smartfactory-erp-incremental-sync-cycle'
        )
        ->cron(
            (string) config(
                'erp-automation.cron',
                '*/15 * * * *'
            )
        )
        ->timezone(
            (string) config(
                'erp-automation.timezone',
                'UTC'
            )
        )
        ->withoutOverlapping(
            (int) config(
                'erp-automation.schedule_mutex_minutes',
                120
            )
        )
        ->onOneServer()
        ->appendOutputTo(
            (string) config(
                'erp-automation.output_file',
                storage_path(
                    'logs/erp-scheduler.log'
                )
            )
        );
}