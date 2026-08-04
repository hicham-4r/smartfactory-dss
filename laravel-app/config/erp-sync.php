<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Page processing
    |--------------------------------------------------------------------------
    */

    'page_size' => (int) env(
        'ERP_SYNC_PAGE_SIZE',
        100
    ),

    /*
     * Safety limit protecting the command from a malformed API that
     * continuously reports another page.
     */
    'maximum_pages_per_resource' => (int) env(
        'ERP_SYNC_MAX_PAGES_PER_RESOURCE',
        10000
    ),

    /*
    |--------------------------------------------------------------------------
    | Synchronization lease
    |--------------------------------------------------------------------------
    */

    'lease_ttl_seconds' => (int) env(
        'ERP_SYNC_LEASE_TTL_SECONDS',
        300
    ),

    /*
    |--------------------------------------------------------------------------
    | Incremental overlap
    |--------------------------------------------------------------------------
    |
    | Re-reading a small time window prevents records from being missed when
    | timestamps are rounded or when records arrive slightly out of order.
    | The persistence layer safely skips duplicate records.
    |
    */

    'overlap_seconds' => (int) env(
        'ERP_SYNC_OVERLAP_SECONDS',
        300
    ),
];