<?php

return [
    'service' => 'sage-erp-simulator',
    'environment' => env('APP_ENV', 'production'),
    'redis_connection' => env(
        'SMARTFACTORY_METRICS_REDIS_CONNECTION',
        'smartfactory_metrics'
    ),
    'redis_host' => env(
        'SMARTFACTORY_METRICS_REDIS_HOST',
        env(
            'REDIS_SERVICE_HOST',
            env('REDIS_HOST', '127.0.0.1')
        )
    ),
    'redis_port' => (int) env(
        'SMARTFACTORY_METRICS_REDIS_PORT',
        env(
            'REDIS_SERVICE_PORT',
            env('REDIS_PORT', 6379)
        )
    ),
    'redis_connect_timeout_seconds' => (float) env(
        'SMARTFACTORY_METRICS_REDIS_CONNECT_TIMEOUT_SECONDS',
        0.5
    ),
    'redis_read_timeout_seconds' => (float) env(
        'SMARTFACTORY_METRICS_REDIS_READ_TIMEOUT_SECONDS',
        0.5
    ),
    'redis_retry_interval_milliseconds' => (int) env(
        'SMARTFACTORY_METRICS_REDIS_RETRY_INTERVAL_MILLISECONDS',
        100
    ),
    'redis_prefix' => env(
        'SMARTFACTORY_METRICS_REDIS_PREFIX',
        'smartfactory:metrics:sage-erp-simulator'
    ),
    'fallback_path' => env(
        'SMARTFACTORY_METRICS_FALLBACK_PATH',
        '/tmp/smartfactory-erp-native-metrics.json'
    ),
    'max_series' => 256,
];
