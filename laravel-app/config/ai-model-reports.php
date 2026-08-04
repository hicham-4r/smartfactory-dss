<?php

return [
    'enabled' => env(
        'AI_INFERENCE_DRIVER',
        'disabled',
    ) === 'fastapi',
    'base_url' => env(
        'AI_SERVICE_BASE_URL',
        'http://127.0.0.1:8001',
    ),
    'token' => env('AI_SERVICE_TOKEN', ''),
    'verify_tls' => filter_var(
        env('AI_SERVICE_VERIFY_TLS', true),
        FILTER_VALIDATE_BOOLEAN,
    ),
    'allow_internal_http' => filter_var(
        env('AI_ALLOW_INTERNAL_HTTP', false),
        FILTER_VALIDATE_BOOLEAN,
    ),
    'connect_timeout_seconds' => (int) env(
        'AI_CONNECT_TIMEOUT_SECONDS',
        2,
    ),
    'timeout_seconds' => (int) env(
        'AI_REQUEST_TIMEOUT_SECONDS',
        5,
    ),
    'maximum_response_bytes' => (int) env(
        'AI_INFERENCE_MAXIMUM_RESPONSE_BYTES',
        262144,
    ),
    'user_agent' => env(
        'AI_USER_AGENT',
        'SmartFactory-DSS/1.0',
    ),
    'metrics_endpoint' => env(
        'AI_INFERENCE_METRICS_ENDPOINT',
        '/internal/v1/inference/models/{model_run_id}/metrics/{task}',
    ),
    'session_key' => 'smartfactory.ai.inference_reports',
    'retention_seconds' => 3600,
    'maximum_reports_per_session' => 5,
];
