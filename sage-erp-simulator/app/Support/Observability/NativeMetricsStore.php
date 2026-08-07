<?php

namespace App\Support\Observability;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class NativeMetricsStore
{
    /** @var array<string,true> */
    private static array $preparedRedisConnections = [];

    /** @var list<float> */
    private const HISTOGRAM_BUCKETS = [
        0.005,
        0.01,
        0.025,
        0.05,
        0.1,
        0.25,
        0.5,
        1.0,
        2.5,
        5.0,
        10.0,
    ];

    public function __construct(
        private readonly string $service,
        private readonly string $environment,
        private readonly string $redisConnection,
        private readonly string $redisPrefix,
        private readonly string $fallbackPath,
        private readonly int $maxSeries = 256,
        private readonly string $redisHost = '',
        private readonly int $redisPort = 6379,
        private readonly float $redisConnectTimeoutSeconds = 0.5,
        private readonly float $redisReadTimeoutSeconds = 0.5,
        private readonly int $redisRetryIntervalMilliseconds = 100,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            service: (string) config(
                'native-metrics.service',
                'smartfactory-service'
            ),
            environment: (string) config(
                'native-metrics.environment',
                'production'
            ),
            redisConnection: (string) config(
                'native-metrics.redis_connection',
                'default'
            ),
            redisPrefix: (string) config(
                'native-metrics.redis_prefix',
                'smartfactory:metrics:service'
            ),
            fallbackPath: (string) config(
                'native-metrics.fallback_path',
                '/tmp/smartfactory-native-metrics.json'
            ),
            maxSeries: max(
                16,
                min(
                    1024,
                    (int) config(
                        'native-metrics.max_series',
                        256
                    )
                )
            ),
            redisHost: trim(
                (string) config(
                    'native-metrics.redis_host',
                    ''
                )
            ),
            redisPort: max(
                1,
                min(
                    65535,
                    (int) config(
                        'native-metrics.redis_port',
                        6379
                    )
                )
            ),
            redisConnectTimeoutSeconds: self::boundedTimeout(
                (float) config(
                    'native-metrics.redis_connect_timeout_seconds',
                    0.5
                )
            ),
            redisReadTimeoutSeconds: self::boundedTimeout(
                (float) config(
                    'native-metrics.redis_read_timeout_seconds',
                    0.5
                )
            ),
            redisRetryIntervalMilliseconds: max(
                0,
                min(
                    1000,
                    (int) config(
                        'native-metrics.redis_retry_interval_milliseconds',
                        100
                    )
                )
            ),
        );
    }

    public function observe(
        string $method,
        string $route,
        int $statusCode,
        float $durationSeconds,
    ): void {
        $labels = $this->normalizedLabels(
            method: $method,
            route: $route,
            statusCode: $statusCode,
        );

        $duration = is_finite($durationSeconds)
            ? max(0.0, $durationSeconds)
            : 0.0;

        try {
            $this->observeRedis($labels, $duration);

            return;
        } catch (Throwable) {
            // Metrics are fail-open and must never break an application request.
        }

        try {
            $this->observeFile($labels, $duration);
        } catch (Throwable) {
            // The application remains authoritative when observability is unavailable.
        }
    }

    public function render(): string
    {
        try {
            return $this->renderState(
                state: $this->readRedisState(),
                backend: 'redis',
                backendUp: true,
            );
        } catch (Throwable) {
            // Fall back to the Pod-local bounded state file.
        }

        try {
            return $this->renderState(
                state: $this->readFileState(),
                backend: 'file',
                backendUp: false,
            );
        } catch (Throwable) {
            return $this->renderState(
                state: $this->emptyState(),
                backend: 'unavailable',
                backendUp: false,
            );
        }
    }

    /**
     * @param array{method:string,route:string,status_class:string} $labels
     */
    private function observeRedis(
        array $labels,
        float $duration,
    ): void {
        $redis = $this->redis();
        $seriesId = $this->seriesId($labels);
        $seriesKey = $this->redisKey('series');

        if (
            ! (bool) $redis->hexists($seriesKey, $seriesId)
            && (int) $redis->hlen($seriesKey) >= $this->maxSeries
        ) {
            $labels['route'] = '__overflow__';
            $seriesId = $this->seriesId($labels);
        }

        $encodedLabels = json_encode(
            $labels,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        $redis->setnx(
            $this->redisKey('started_at'),
            (string) time()
        );
        $redis->hsetnx($seriesKey, $seriesId, $encodedLabels);
        $redis->hincrby(
            $this->redisKey('requests'),
            $seriesId,
            1
        );
        $redis->hincrbyfloat(
            $this->redisKey('duration_sum'),
            $seriesId,
            $duration
        );

        foreach (self::HISTOGRAM_BUCKETS as $bucket) {
            if ($duration <= $bucket) {
                $redis->hincrby(
                    $this->redisKey(
                        'bucket:'.$this->bucketLabel($bucket)
                    ),
                    $seriesId,
                    1
                );
            }
        }

        $redis->hincrby(
            $this->redisKey('bucket:+Inf'),
            $seriesId,
            1
        );
    }

    /**
     * @param array{method:string,route:string,status_class:string} $labels
     */
    private function observeFile(
        array $labels,
        float $duration,
    ): void {
        $this->withLockedFile(
            exclusive: true,
            callback: function (array $state) use (
                $labels,
                $duration
            ): array {
                $seriesId = $this->seriesId($labels);
                $effectiveLabels = $labels;

                if (
                    ! isset($state['series'][$seriesId])
                    && count($state['series']) >= $this->maxSeries
                ) {
                    $effectiveLabels['route'] = '__overflow__';
                    $seriesId = $this->seriesId($effectiveLabels);
                }

                $series = $state['series'][$seriesId] ?? [
                    'labels' => $effectiveLabels,
                    'count' => 0,
                    'duration_sum' => 0.0,
                    'buckets' => [],
                ];

                $series['count'] = (int) $series['count'] + 1;
                $series['duration_sum'] =
                    (float) $series['duration_sum'] + $duration;

                foreach (self::HISTOGRAM_BUCKETS as $bucket) {
                    if ($duration <= $bucket) {
                        $label = $this->bucketLabel($bucket);
                        $series['buckets'][$label] =
                            (int) ($series['buckets'][$label] ?? 0) + 1;
                    }
                }

                $series['buckets']['+Inf'] =
                    (int) ($series['buckets']['+Inf'] ?? 0) + 1;
                $state['series'][$seriesId] = $series;

                return $state;
            }
        );
    }

    /**
     * @return array{started_at:int,series:array<string,array<string,mixed>>}
     */
    private function readRedisState(): array
    {
        $redis = $this->redis();
        $labelsBySeries = $redis->hgetall(
            $this->redisKey('series')
        );
        $counts = $redis->hgetall(
            $this->redisKey('requests')
        );
        $durationSums = $redis->hgetall(
            $this->redisKey('duration_sum')
        );

        /*
         * Load every histogram bucket hash once.
         *
         * The previous implementation performed one Redis HGET for every
         * bucket of every series while rendering. At the bounded maximum of
         * 256 series, that produced more than 3,000 sequential Redis round
         * trips for a single Prometheus scrape and could exceed the scrape
         * timeout. This implementation performs a fixed number of HGETALL
         * operations independent of the series count.
         *
         * @var array<string,array<string,int|string|float>> $bucketCounts
         */
        $bucketCounts = [];

        foreach (self::HISTOGRAM_BUCKETS as $bucket) {
            $label = $this->bucketLabel($bucket);
            $bucketCounts[$label] = $redis->hgetall(
                $this->redisKey('bucket:'.$label)
            );
        }

        $bucketCounts['+Inf'] = $redis->hgetall(
            $this->redisKey('bucket:+Inf')
        );

        $state = [
            'started_at' => (int) (
                $redis->get($this->redisKey('started_at'))
                ?: time()
            ),
            'series' => [],
        ];

        foreach ($labelsBySeries as $seriesId => $encodedLabels) {
            $labels = json_decode(
                (string) $encodedLabels,
                true,
                8,
                JSON_THROW_ON_ERROR
            );

            if (! is_array($labels)) {
                continue;
            }

            $buckets = [];
            foreach (self::HISTOGRAM_BUCKETS as $bucket) {
                $label = $this->bucketLabel($bucket);
                $buckets[$label] = (int) (
                    $bucketCounts[$label][$seriesId] ?? 0
                );
            }
            $buckets['+Inf'] = (int) (
                $bucketCounts['+Inf'][$seriesId] ?? 0
            );

            $state['series'][(string) $seriesId] = [
                'labels' => $labels,
                'count' => (int) ($counts[$seriesId] ?? 0),
                'duration_sum' => (float) (
                    $durationSums[$seriesId] ?? 0.0
                ),
                'buckets' => $buckets,
            ];
        }

        return $state;
    }

    private function redis(): Connection
    {
        if (
            $this->redisConnection !== 'smartfactory_metrics'
        ) {
            return Redis::connection(
                $this->redisConnection
            );
        }

        if (
            ! isset(
                self::$preparedRedisConnections[
                    $this->redisConnection
                ]
            )
        ) {
            $default = config(
                'database.redis.default',
                []
            );
            if (! is_array($default)) {
                $default = [];
            }

            $host = $this->redisHost !== ''
                ? $this->redisHost
                : trim(
                    (string) (
                        $default['host']
                        ?? '127.0.0.1'
                    )
                );

            config([
                'database.redis.'.$this->redisConnection => array_merge(
                    $default,
                    [
                        'host' => $host !== ''
                            ? $host
                            : '127.0.0.1',
                        'port' => $this->redisPort,
                        'timeout' =>
                            $this->redisConnectTimeoutSeconds,
                        'read_timeout' =>
                            $this->redisReadTimeoutSeconds,
                        'retry_interval' =>
                            $this->redisRetryIntervalMilliseconds,
                        'persistent' => false,
                    ],
                ),
            ]);

            Redis::purge($this->redisConnection);
            self::$preparedRedisConnections[
                $this->redisConnection
            ] = true;
        }

        return Redis::connection(
            $this->redisConnection
        );
    }

    private static function boundedTimeout(
        float $seconds,
    ): float {
        if (! is_finite($seconds)) {
            return 0.5;
        }

        return max(0.05, min(2.0, $seconds));
    }

    /**
     * @return array{started_at:int,series:array<string,array<string,mixed>>}
     */
    private function readFileState(): array
    {
        return $this->withLockedFile(
            exclusive: false,
            callback: static fn (array $state): array => $state,
        );
    }

    /**
     * @param callable(array{started_at:int,series:array<string,array<string,mixed>>}):array{started_at:int,series:array<string,array<string,mixed>>} $callback
     * @return array{started_at:int,series:array<string,array<string,mixed>>}
     */
    private function withLockedFile(
        bool $exclusive,
        callable $callback,
    ): array {
        $directory = dirname($this->fallbackPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $handle = fopen($this->fallbackPath, 'c+');
        if ($handle === false) {
            throw new \RuntimeException(
                'Unable to open the native-metrics fallback state.'
            );
        }

        try {
            if (! flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
                throw new \RuntimeException(
                    'Unable to lock the native-metrics fallback state.'
                );
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = $this->decodeState(
                is_string($raw) ? $raw : ''
            );
            $state = $callback($state);

            if ($exclusive) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite(
                    $handle,
                    json_encode(
                        $state,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_SLASHES
                    )
                );
                fflush($handle);
                @chmod($this->fallbackPath, 0600);
            }

            flock($handle, LOCK_UN);

            return $state;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{started_at:int,series:array<string,array<string,mixed>>}
     */
    private function decodeState(string $raw): array
    {
        if (trim($raw) === '') {
            return $this->emptyState();
        }

        try {
            $state = json_decode(
                $raw,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return $this->emptyState();
        }

        if (
            ! is_array($state)
            || ! isset($state['series'])
            || ! is_array($state['series'])
        ) {
            return $this->emptyState();
        }

        return [
            'started_at' => max(
                0,
                (int) ($state['started_at'] ?? time())
            ),
            'series' => $state['series'],
        ];
    }

    /**
     * @return array{started_at:int,series:array<string,array<string,mixed>>}
     */
    private function emptyState(): array
    {
        return [
            'started_at' => time(),
            'series' => [],
        ];
    }

    /**
     * @param array{started_at:int,series:array<string,array<string,mixed>>} $state
     */
    private function renderState(
        array $state,
        string $backend,
        bool $backendUp,
    ): string {
        $serviceLabel = $this->escapeLabel($this->service);
        $environmentLabel = $this->escapeLabel(
            $this->environment
        );
        $backendLabel = $this->escapeLabel($backend);

        $lines = [
            '# HELP smartfactory_application_info Static application identity.',
            '# TYPE smartfactory_application_info gauge',
            sprintf(
                'smartfactory_application_info{service="%s",environment="%s",runtime="php"} 1',
                $serviceLabel,
                $environmentLabel,
            ),
            '# HELP smartfactory_metrics_backend_up Whether the shared Redis metrics backend is available.',
            '# TYPE smartfactory_metrics_backend_up gauge',
            sprintf(
                'smartfactory_metrics_backend_up{service="%s",backend="%s"} %d',
                $serviceLabel,
                $backendLabel,
                $backendUp ? 1 : 0,
            ),
            '# HELP smartfactory_metrics_state_started_timestamp_seconds Metrics-state start timestamp.',
            '# TYPE smartfactory_metrics_state_started_timestamp_seconds gauge',
            sprintf(
                'smartfactory_metrics_state_started_timestamp_seconds{service="%s"} %d',
                $serviceLabel,
                max(0, (int) $state['started_at']),
            ),
            '# HELP smartfactory_http_requests_total Total HTTP requests by bounded route and status class.',
            '# TYPE smartfactory_http_requests_total counter',
            '# HELP smartfactory_http_request_duration_seconds HTTP request duration histogram.',
            '# TYPE smartfactory_http_request_duration_seconds histogram',
        ];

        ksort($state['series']);

        foreach ($state['series'] as $series) {
            if (! is_array($series)) {
                continue;
            }

            $labels = is_array($series['labels'] ?? null)
                ? $series['labels']
                : [];
            $baseLabels = $this->renderLabels([
                'service' => $this->service,
                'method' => (string) ($labels['method'] ?? 'UNKNOWN'),
                'route' => (string) ($labels['route'] ?? 'unknown'),
                'status_class' => (string) (
                    $labels['status_class'] ?? '5xx'
                ),
            ]);
            $count = max(0, (int) ($series['count'] ?? 0));
            $durationSum = max(
                0.0,
                (float) ($series['duration_sum'] ?? 0.0)
            );
            $buckets = is_array($series['buckets'] ?? null)
                ? $series['buckets']
                : [];

            $lines[] = sprintf(
                'smartfactory_http_requests_total{%s} %d',
                $baseLabels,
                $count,
            );

            foreach (self::HISTOGRAM_BUCKETS as $bucket) {
                $bucketLabel = $this->bucketLabel($bucket);
                $lines[] = sprintf(
                    'smartfactory_http_request_duration_seconds_bucket{%s,le="%s"} %d',
                    $baseLabels,
                    $bucketLabel,
                    max(0, (int) ($buckets[$bucketLabel] ?? 0)),
                );
            }

            $lines[] = sprintf(
                'smartfactory_http_request_duration_seconds_bucket{%s,le="+Inf"} %d',
                $baseLabels,
                max(0, (int) ($buckets['+Inf'] ?? $count)),
            );
            $lines[] = sprintf(
                'smartfactory_http_request_duration_seconds_sum{%s} %s',
                $baseLabels,
                $this->formatFloat($durationSum),
            );
            $lines[] = sprintf(
                'smartfactory_http_request_duration_seconds_count{%s} %d',
                $baseLabels,
                $count,
            );
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{method:string,route:string,status_class:string}
     */
    private function normalizedLabels(
        string $method,
        string $route,
        int $statusCode,
    ): array {
        $normalizedMethod = strtoupper(trim($method));
        if (! preg_match('/^[A-Z]{1,12}$/', $normalizedMethod)) {
            $normalizedMethod = 'OTHER';
        }

        $normalizedRoute = trim($route);
        $normalizedRoute = preg_replace(
            '/[^A-Za-z0-9_.:()\/{\}-]+/',
            '_',
            $normalizedRoute
        ) ?? 'unknown';
        $normalizedRoute = trim($normalizedRoute, '_');
        if ($normalizedRoute === '') {
            $normalizedRoute = 'unknown';
        }
        $normalizedRoute = mb_substr($normalizedRoute, 0, 160);

        $statusClass = $statusCode >= 100 && $statusCode <= 599
            ? intdiv($statusCode, 100).'xx'
            : '5xx';

        return [
            'method' => $normalizedMethod,
            'route' => $normalizedRoute,
            'status_class' => $statusClass,
        ];
    }

    /**
     * @param array{method:string,route:string,status_class:string} $labels
     */
    private function seriesId(array $labels): string
    {
        return hash(
            'sha256',
            json_encode(
                $labels,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            )
        );
    }

    private function redisKey(string $suffix): string
    {
        return rtrim($this->redisPrefix, ':').':'.$suffix;
    }

    private function bucketLabel(float $bucket): string
    {
        return rtrim(rtrim(sprintf('%.3f', $bucket), '0'), '.');
    }

    /** @param array<string,string> $labels */
    private function renderLabels(array $labels): string
    {
        $rendered = [];
        foreach ($labels as $name => $value) {
            $rendered[] = sprintf(
                '%s="%s"',
                $name,
                $this->escapeLabel($value),
            );
        }

        return implode(',', $rendered);
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(
            ["\\", "\n", '"'],
            ["\\\\", '\\n', '\\"'],
            $value
        );
    }

    private function formatFloat(float $value): string
    {
        return rtrim(
            rtrim(sprintf('%.9F', $value), '0'),
            '.'
        ) ?: '0';
    }
}
