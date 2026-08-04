<?php

namespace App\Services\AI\Inference;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

final readonly class AiModelMetricsClient
{
    private const TASKS = [
        'production_forecasting',
        'production_anomaly',
        'maintenance_risk',
    ];

    public function __construct(
        private Factory $http,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(
        string $modelRunId,
        string $task,
        string $requestId,
    ): ?array {
        if (
            ! Str::isUuid($modelRunId)
            || ! in_array($task, self::TASKS, true)
            || ! $this->validRequestId($requestId)
        ) {
            return null;
        }

        $settings = $this->settings();
        if ($settings === null) {
            return null;
        }

        $endpoint = str_replace(
            ['{model_run_id}', '{task}'],
            [rawurlencode($modelRunId), rawurlencode($task)],
            $settings['metrics_endpoint'],
        );

        try {
            $response = $this->http
                ->baseUrl($settings['base_url'])
                ->acceptJson()
                ->withToken($settings['token'])
                ->withHeaders([
                    'X-Request-ID' => $requestId,
                    'User-Agent' => $settings['user_agent'],
                ])
                ->connectTimeout($settings['connect_timeout_seconds'])
                ->timeout($settings['timeout_seconds'])
                ->withOptions([
                    'verify' => $settings['verify_tls'],
                ])
                ->get($endpoint);
        } catch (Throwable) {
            return null;
        }

        if (
            ! $response->successful()
            || strlen($response->body()) > $settings['maximum_response_bytes']
        ) {
            return null;
        }

        try {
            $payload = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        return $this->validate(
            payload: $payload,
            modelRunId: $modelRunId,
            task: $task,
            requestId: $requestId,
        );
    }

    /**
     * @return array{
     *     base_url:string,
     *     token:string,
     *     verify_tls:bool,
     *     connect_timeout_seconds:int,
     *     timeout_seconds:int,
     *     maximum_response_bytes:int,
     *     user_agent:string,
     *     metrics_endpoint:string
     * }|null
     */
    private function settings(): ?array
    {
        if (! (bool) config('ai-model-reports.enabled', false)) {
            return null;
        }

        $baseUrl = config('ai-model-reports.base_url');
        $token = config('ai-model-reports.token');
        $verifyTls = config('ai-model-reports.verify_tls', true);
        $allowInternalHttp = config(
            'ai-model-reports.allow_internal_http',
            false,
        );
        $connectTimeout = filter_var(
            config('ai-model-reports.connect_timeout_seconds'),
            FILTER_VALIDATE_INT,
        );
        $timeout = filter_var(
            config('ai-model-reports.timeout_seconds'),
            FILTER_VALIDATE_INT,
        );
        $maximumResponseBytes = filter_var(
            config('ai-model-reports.maximum_response_bytes'),
            FILTER_VALIDATE_INT,
        );
        $userAgent = config('ai-model-reports.user_agent');
        $endpoint = config('ai-model-reports.metrics_endpoint');

        if (
            ! is_string($baseUrl)
            || ! is_string($token)
            || ! is_bool($verifyTls)
            || ! is_bool($allowInternalHttp)
            || ! is_string($userAgent)
            || ! is_string($endpoint)
        ) {
            return null;
        }

        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);
        $scheme = is_array($parts)
            ? strtolower((string) ($parts['scheme'] ?? ''))
            : '';

        if (
            filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || ! in_array($scheme, ['http', 'https'], true)
            || ($scheme === 'http' && ! $allowInternalHttp)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! isset($parts['host'])
        ) {
            return null;
        }

        $token = trim($token);
        $userAgent = trim($userAgent);
        if (
            strlen($token) < 32
            || strlen($token) > 4096
            || preg_match('/[\x00-\x1F\x7F]/', $token) === 1
            || $userAgent === ''
            || strlen($userAgent) > 200
            || preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1
            || ! $this->safeEndpoint($endpoint)
            || $connectTimeout === false
            || $connectTimeout < 1
            || $connectTimeout > 30
            || $timeout === false
            || $timeout < $connectTimeout
            || $timeout > 120
            || $maximumResponseBytes === false
            || $maximumResponseBytes < 1024
            || $maximumResponseBytes > 10485760
        ) {
            return null;
        }

        return [
            'base_url' => $baseUrl,
            'token' => $token,
            'verify_tls' => $verifyTls,
            'connect_timeout_seconds' => $connectTimeout,
            'timeout_seconds' => $timeout,
            'maximum_response_bytes' => $maximumResponseBytes,
            'user_agent' => $userAgent,
            'metrics_endpoint' => $endpoint,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function validate(
        array $payload,
        string $modelRunId,
        string $task,
        string $requestId,
    ): ?array {
        $metrics = $payload['metrics'] ?? null;
        $limitations = $payload['limitations'] ?? null;
        $derivations = $payload['metric_derivations'] ?? null;

        if (
            ($payload['status'] ?? null) !== 'ok'
            || ($payload['model_run_id'] ?? null) !== $modelRunId
            || ! is_string($payload['source_feature_run_id'] ?? null)
            || ! Str::isUuid($payload['source_feature_run_id'])
            || ($payload['task'] ?? null) !== $task
            || ! is_string($payload['selected_model'] ?? null)
            || trim((string) $payload['selected_model']) === ''
            || ($payload['data_classification'] ?? null) !== 'simulated_prototype'
            || ($payload['request_id'] ?? null) !== $requestId
            || ! is_array($metrics)
            || ! is_array($limitations)
            || ! is_array($derivations)
        ) {
            return null;
        }

        if (
            ! $this->safeTree($metrics, 0)
            || ! $this->safeTree($derivations, 0)
            || ! $this->safeStringList($limitations)
        ) {
            return null;
        }

        return [
            'status' => 'ok',
            'model_run_id' => $modelRunId,
            'source_feature_run_id' => $payload['source_feature_run_id'],
            'task' => $task,
            'selected_model' => trim((string) $payload['selected_model']),
            'data_classification' => 'simulated_prototype',
            'metrics' => $metrics,
            'metric_derivations' => $derivations,
            'limitations' => array_values($limitations),
            'request_id' => $requestId,
        ];
    }

    private function safeEndpoint(string $endpoint): bool
    {
        return str_starts_with($endpoint, '/')
            && ! str_starts_with($endpoint, '//')
            && ! str_contains($endpoint, '?')
            && ! str_contains($endpoint, '#')
            && preg_match('/[\x00-\x1F\x7F]/', $endpoint) !== 1
            && substr_count($endpoint, '{model_run_id}') === 1
            && substr_count($endpoint, '{task}') === 1;
    }

    private function validRequestId(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 100
            && preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1;
    }

    /**
     * @param array<int, mixed> $values
     */
    private function safeStringList(array $values): bool
    {
        if ($values === [] || count($values) > 30) {
            return false;
        }

        foreach ($values as $value) {
            if (
                ! is_string($value)
                || trim($value) === ''
                || strlen($value) > 1000
            ) {
                return false;
            }
        }

        return true;
    }

    private function safeTree(mixed $value, int $depth): bool
    {
        if ($depth > 10) {
            return false;
        }

        if (
            $value === null
            || is_bool($value)
            || is_int($value)
            || (is_float($value) && is_finite($value))
        ) {
            return true;
        }

        if (is_string($value)) {
            return strlen($value) <= 2000
                && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
        }

        if (! is_array($value) || count($value) > 500) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (
                (! is_int($key) && (! is_string($key) || strlen($key) > 200))
                || ! $this->safeTree($item, $depth + 1)
            ) {
                return false;
            }
        }

        return true;
    }
}
