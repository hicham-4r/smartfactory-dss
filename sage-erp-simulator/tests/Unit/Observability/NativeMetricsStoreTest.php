<?php

namespace Tests\Unit\Observability;

use App\Support\Observability\NativeMetricsStore;
use PHPUnit\Framework\TestCase;

final class NativeMetricsStoreTest extends TestCase
{
    public function test_it_renders_bounded_prometheus_metrics_without_redis(): void
    {
        $path = sys_get_temp_dir().'/smartfactory-native-metrics-'.bin2hex(random_bytes(8)).'.json';
        $store = new NativeMetricsStore(
            service: 'test-service',
            environment: 'testing',
            redisConnection: 'missing-test-connection',
            redisPrefix: 'smartfactory:test:metrics',
            fallbackPath: $path,
            maxSeries: 16,
        );

        $store->observe('GET', 'orders.show', 200, 0.125);
        $metrics = $store->render();

        self::assertStringContainsString(
            'smartfactory_application_info{service="test-service"',
            $metrics
        );
        self::assertStringContainsString(
            'smartfactory_http_requests_total{service="test-service",method="GET",route="orders.show",status_class="2xx"} 1',
            $metrics
        );
        self::assertStringContainsString(
            'smartfactory_http_request_duration_seconds_count',
            $metrics
        );
        self::assertStringNotContainsString('password', $metrics);

        @unlink($path);
        @unlink($path.'.lock');
    }

    public function test_constructor_preserves_a_custom_connection_for_fallback_testing(): void
    {
        $path = sys_get_temp_dir().'/smartfactory-native-metrics-'.bin2hex(random_bytes(8)).'.json';
        $store = new NativeMetricsStore(
            service: 'test-service',
            environment: 'testing',
            redisConnection: 'missing-test-connection',
            redisPrefix: 'smartfactory:test:metrics',
            fallbackPath: $path,
            maxSeries: 16,
            redisHost: '127.0.0.1',
            redisPort: 1,
            redisConnectTimeoutSeconds: 0.05,
            redisReadTimeoutSeconds: 0.05,
            redisRetryIntervalMilliseconds: 0,
        );

        $started = microtime(true);
        $store->observe('GET', 'health', 200, 0.001);
        $metrics = $store->render();
        $elapsed = microtime(true) - $started;

        self::assertLessThan(2.0, $elapsed);
        self::assertStringContainsString(
            'smartfactory_metrics_backend_up{service="test-service",backend="file"} 0',
            $metrics
        );

        @unlink($path);
        @unlink($path.'.lock');
    }
}
