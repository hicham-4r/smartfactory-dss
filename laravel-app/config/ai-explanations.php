<?php

return [
    'default' => env(
        'AI_EXPLANATION_DRIVER',
        env('AI_SERVICE_DRIVER', 'disabled'),
    ),

    'allow_internal_http' => (bool) env(
        'AI_EXPLANATION_ALLOW_INTERNAL_HTTP',
        env('AI_ALLOW_INTERNAL_HTTP', false),
    ),

    'service' => [
        'base_url' => env(
            'AI_EXPLANATION_BASE_URL',
            env('AI_SERVICE_BASE_URL', 'http://127.0.0.1:8001'),
        ),

        'token' => env('AI_EXPLANATION_TOKEN')
            ?: env('AI_SERVICE_TOKEN', ''),

        'verify_tls' => (bool) env(
            'AI_EXPLANATION_VERIFY_TLS',
            env('AI_SERVICE_VERIFY_TLS', true),
        ),

        'endpoint' => env(
            'AI_EXPLANATION_ENDPOINT',
            '/internal/v1/explanations/generate',
        ),

        'connect_timeout_seconds' => (int) env(
            'AI_EXPLANATION_CONNECT_TIMEOUT_SECONDS',
            2,
        ),

        'timeout_seconds' => (int) env(
            'AI_EXPLANATION_TIMEOUT_SECONDS',
            90,
        ),

        'maximum_request_bytes' => (int) env(
            'AI_EXPLANATION_MAXIMUM_REQUEST_BYTES',
            65536,
        ),

        'maximum_response_bytes' => (int) env(
            'AI_EXPLANATION_MAXIMUM_RESPONSE_BYTES',
            65536,
        ),

        'user_agent' => env(
            'AI_EXPLANATION_USER_AGENT',
            'SmartFactory-DSS/1.0',
        ),

        'log_channel' => env(
            'AI_EXPLANATION_LOG_CHANNEL',
            env('AI_LOG_CHANNEL', 'stack'),
        ),
    ],

    'snapshot_ttl_minutes' => (int) env(
        'AI_EXPLANATION_SNAPSHOT_TTL_MINUTES',
        15,
    ),

    'maximum_session_snapshots' => (int) env(
        'AI_EXPLANATION_MAXIMUM_SESSION_SNAPSHOTS',
        10,
    ),
];
