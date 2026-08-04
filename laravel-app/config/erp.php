<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active ERP connector
    |--------------------------------------------------------------------------
    */

    'default' => env(
        'ERP_CONNECTOR',
        'disabled'
    ),

    /*
    |--------------------------------------------------------------------------
    | Outbound ERP connectors
    |--------------------------------------------------------------------------
    */

    'connectors' => [
        'simulated_sage' => [
            'source_system' =>
                'simulated_sage',

            /*
             * The ERP simulator is a separate Laravel application.
             */
            'base_url' => env(
                'ERP_SIMULATED_SAGE_BASE_URL',
                'https://sage-erp-simulator.test'
            ),

            /*
             * The DSS uses its own environment-variable name.
             * Its value must match ERP_API_TOKEN in the simulator.
             */
            'token' => env(
                'ERP_SIMULATED_SAGE_TOKEN'
            ),

            'verify_tls' => filter_var(
                env(
                    'ERP_SIMULATED_SAGE_VERIFY_TLS',
                    true
                ),
                FILTER_VALIDATE_BOOL
            ),

            'health_endpoint' => env(
                'ERP_SIMULATED_SAGE_HEALTH_ENDPOINT',
                '/api/health'
            ),

            'connect_timeout_seconds' =>
                (int) env(
                    'ERP_CONNECT_TIMEOUT_SECONDS',
                    5
                ),

            'timeout_seconds' =>
                (int) env(
                    'ERP_REQUEST_TIMEOUT_SECONDS',
                    20
                ),

            'retry_attempts' =>
                (int) env(
                    'ERP_RETRY_ATTEMPTS',
                    3
                ),

            'retry_delay_milliseconds' =>
                (int) env(
                    'ERP_RETRY_DELAY_MILLISECONDS',
                    250
                ),

            'retry_maximum_delay_milliseconds' =>
                (int) env(
                    'ERP_RETRY_MAXIMUM_DELAY_MILLISECONDS',
                    5000
                ),

            'page_size' =>
                (int) env(
                    'ERP_PAGE_SIZE',
                    100
                ),

            'maximum_page_size' =>
                (int) env(
                    'ERP_MAXIMUM_PAGE_SIZE',
                    100
                ),

            'maximum_response_bytes' =>
                (int) env(
                    'ERP_MAXIMUM_RESPONSE_BYTES',
                    5242880
                ),

            'user_agent' => env(
                'ERP_USER_AGENT',
                'SmartFactory-DSS/1.0'
            ),

            /*
             * ErpResource names remain stable inside the DSS.
             * Only the remote simulator paths differ.
             */
            'endpoints' => [
                'product_families' =>
                    '/api/product-families',

                'products' =>
                    '/api/products',

                'production_lines' =>
                    '/api/production-lines',

                'machines' =>
                    '/api/machines',

                'shifts' =>
                    '/api/shifts',

                'operators' =>
                    '/api/operators',

                'operator_assignments' =>
                    '/api/operator-assignments',

                /*
                 * Internal resource WorkOrders maps to the simulator's
                 * production-orders endpoint.
                 */
                'work_orders' =>
                    '/api/production-orders',

                /*
                 * Internal resource Batches maps to the simulator's
                 * production-batches endpoint.
                 */
                'batches' =>
                    '/api/production-batches',

                /*
                 * Internal resource MachineRuns maps to the simulator's
                 * production-records endpoint.
                 */
                'machine_runs' =>
                    '/api/production-records',



                'downtime_events' =>
                    '/api/downtime-events',

                'machine_status_events' =>
                    '/api/machine-status-events',

                'maintenance_history' =>
                    '/api/maintenance-history',

                'inspections' =>
                    '/api/inspections',

                'nonconformities' =>
                    '/api/nonconformities',

                'finished_lots' =>
                    '/api/finished-lots',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected DSS-local simulator server
    |--------------------------------------------------------------------------
    |
    | Retained for compatibility with the protected endpoints that were
    | previously implemented in the DSS. It does not configure outbound
    | calls to the separate Sage simulator.
    |
    */

    'simulator' => [
        'token' => env(
            'ERP_SIMULATOR_API_TOKEN'
        ),

        'enforce_https' => filter_var(
            env(
                'ERP_SIMULATOR_ENFORCE_HTTPS',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        'maximum_page_size' =>
            (int) env(
                'ERP_SIMULATOR_MAXIMUM_PAGE_SIZE',
                200
            ),

        'cursor_ttl_seconds' =>
            (int) env(
                'ERP_SIMULATOR_CURSOR_TTL_SECONDS',
                3600
            ),

        'rate_limit_per_minute' =>
            (int) env(
                'ERP_SIMULATOR_RATE_LIMIT_PER_MINUTE',
                120
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Incremental synchronization
    |--------------------------------------------------------------------------
    */

    'incremental' => [
        'overlap_seconds' =>
            (int) env(
                'ERP_SYNC_OVERLAP_SECONDS',
                300
            ),

        'initial_lookback_days' =>
            (int) env(
                'ERP_INITIAL_LOOKBACK_DAYS',
                30
            ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safe ERP logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env(
            'ERP_LOG_CHANNEL',
            'stack'
        ),

        /*
         * Tokens, authentication headers, and source payloads must
         * never be written to application logs.
         */
        'include_payloads' => false,
    ],
];