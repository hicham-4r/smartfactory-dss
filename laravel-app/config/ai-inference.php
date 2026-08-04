<?php

return [
    'default' => env(
        'AI_INFERENCE_DRIVER',
        env('AI_SERVICE_DRIVER', 'disabled'),
    ),

    'allow_internal_http' => env('AI_ALLOW_INTERNAL_HTTP', false),

    'service' => [
        'base_url' => env(
            'AI_SERVICE_BASE_URL',
            'http://127.0.0.1:8001',
        ),
        'token' => env('AI_SERVICE_TOKEN', ''),
        'verify_tls' => env('AI_SERVICE_VERIFY_TLS', true),
        'models_endpoint' => env(
            'AI_INFERENCE_MODELS_ENDPOINT',
            '/internal/v1/inference/models',
        ),
        'forecast_endpoint' => env(
            'AI_INFERENCE_FORECAST_ENDPOINT',
            '/internal/v1/inference/production/forecast',
        ),
        'anomaly_endpoint' => env(
            'AI_INFERENCE_ANOMALY_ENDPOINT',
            '/internal/v1/inference/production/anomaly',
        ),
        'maintenance_risk_endpoint' => env(
            'AI_INFERENCE_MAINTENANCE_RISK_ENDPOINT',
            '/internal/v1/inference/maintenance/risk',
        ),
        'connect_timeout_seconds' => env(
            'AI_CONNECT_TIMEOUT_SECONDS',
            2,
        ),
        'timeout_seconds' => env(
            'AI_REQUEST_TIMEOUT_SECONDS',
            5,
        ),
        'retry_attempts' => env('AI_RETRY_ATTEMPTS', 2),
        'retry_delay_milliseconds' => env(
            'AI_RETRY_DELAY_MILLISECONDS',
            150,
        ),
        'maximum_request_bytes' => env(
            'AI_INFERENCE_MAXIMUM_REQUEST_BYTES',
            262144,
        ),
        'maximum_response_bytes' => env(
            'AI_INFERENCE_MAXIMUM_RESPONSE_BYTES',
            262144,
        ),
        'user_agent' => env(
            'AI_USER_AGENT',
            'SmartFactory-DSS/1.0',
        ),
        'log_channel' => env('AI_LOG_CHANNEL', 'stack'),
    ],
];
