<?php

return [
    'default' => env(
        'AI_SERVICE_DRIVER',
        'disabled'
    ),

    /*
     * Native Windows development uses loopback HTTP.
     * Container and production environments must opt in explicitly
     * when they rely on a trusted private network without TLS.
     */
    'allow_internal_http' => (bool) env(
        'AI_ALLOW_INTERNAL_HTTP',
        false
    ),

    'service' => [
        'base_url' => env(
            'AI_SERVICE_BASE_URL',
            'http://127.0.0.1:8001'
        ),

        'token' => env(
            'AI_SERVICE_TOKEN',
            ''
        ),

        'verify_tls' => (bool) env(
            'AI_SERVICE_VERIFY_TLS',
            true
        ),

        'health_endpoint' => env(
            'AI_SERVICE_HEALTH_ENDPOINT',
            '/health/ready'
        ),

        'version_endpoint' => env(
            'AI_SERVICE_VERSION_ENDPOINT',
            '/version'
        ),

        'analytics_contract_endpoint' => env(
            'AI_SERVICE_ANALYTICS_CONTRACT_ENDPOINT',
            '/internal/v1/contracts/analytics/validate'
        ),

        'connect_timeout_seconds' => (int) env(
            'AI_CONNECT_TIMEOUT_SECONDS',
            2
        ),

        'timeout_seconds' => (int) env(
            'AI_REQUEST_TIMEOUT_SECONDS',
            5
        ),

        'retry_attempts' => (int) env(
            'AI_RETRY_ATTEMPTS',
            2
        ),

        'retry_delay_milliseconds' => (int) env(
            'AI_RETRY_DELAY_MILLISECONDS',
            150
        ),

        'maximum_request_bytes' => (int) env(
            'AI_MAXIMUM_REQUEST_BYTES',
            1048576
        ),

        'maximum_response_bytes' => (int) env(
            'AI_MAXIMUM_RESPONSE_BYTES',
            262144
        ),

        'user_agent' => env(
            'AI_USER_AGENT',
            'SmartFactory-DSS/1.0'
        ),

        'log_channel' => env(
            'AI_LOG_CHANNEL',
            'stack'
        ),
    ],
];
