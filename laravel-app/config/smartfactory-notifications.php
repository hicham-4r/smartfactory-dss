<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database notification retention
    |--------------------------------------------------------------------------
    |
    | Read notifications older than this period may be removed with the
    | notifications:prune command. Unread notifications are retained unless
    | --include-unread is explicitly supplied.
    |
    */
    'retention_days' =>
        (int) env(
            'SMARTFACTORY_NOTIFICATION_RETENTION_DAYS',
            90
        ),

    /*
    |--------------------------------------------------------------------------
    | Deterministic critical-event lookback
    |--------------------------------------------------------------------------
    |
    | On-demand alert evaluation inspects unresolved critical production
    | events created inside this period.
    |
    */
    'critical_event_lookback_days' =>
        (int) env(
            'SMARTFACTORY_CRITICAL_EVENT_LOOKBACK_DAYS',
            30
        ),
];
