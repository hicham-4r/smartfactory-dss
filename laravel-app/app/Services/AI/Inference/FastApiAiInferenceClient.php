<?php

namespace App\Services\AI\Inference;

use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Inference\AiInferenceConfig;
use App\DTOs\AI\Inference\AiInferenceResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final readonly class FastApiAiInferenceClient implements AiInferenceClientInterface
{
    private const EXPECTED_TASKS = [
        'production_forecasting',
        'production_anomaly',
        'maintenance_risk',
    ];

    public function __construct(
        private Factory $http,
        private AiInferenceConfig $config,
    ) {}

    public function models(string $requestId): AiInferenceResult
    {
        return $this->execute(
            method: 'GET',
            endpoint: $this->config->modelsEndpoint,
            payload: [],
            operation: 'models',
            requestId: $requestId,
        );
    }

    public function forecast(
        array $payload,
        string $requestId,
    ): AiInferenceResult {
        return $this->execute(
            method: 'POST',
            endpoint: $this->config->forecastEndpoint,
            payload: $payload,
            operation: 'production_forecast',
            requestId: $requestId,
        );
    }

    public function anomaly(
        array $payload,
        string $requestId,
    ): AiInferenceResult {
        return $this->execute(
            method: 'POST',
            endpoint: $this->config->anomalyEndpoint,
            payload: $payload,
            operation: 'production_anomaly',
            requestId: $requestId,
        );
    }

    public function maintenanceRisk(
        array $payload,
        string $requestId,
    ): AiInferenceResult {
        return $this->execute(
            method: 'POST',
            endpoint: $this->config->maintenanceRiskEndpoint,
            payload: $payload,
            operation: 'maintenance_risk',
            requestId: $requestId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function execute(
        string $method,
        string $endpoint,
        array $payload,
        string $operation,
        string $requestId,
    ): AiInferenceResult {
        if (! $this->validRequestId($requestId)) {
            return AiInferenceResult::rejected(
                operation: $operation,
                requestId: $requestId,
                message: 'The internal request identifier is invalid.',
            );
        }

        if ($method === 'POST') {
            try {
                $encoded = json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE,
                );
            } catch (JsonException) {
                return AiInferenceResult::rejected(
                    operation: $operation,
                    requestId: $requestId,
                    message: 'The inference request could not be encoded safely.',
                );
            }

            if (strlen($encoded) > $this->config->maximumRequestBytes) {
                return AiInferenceResult::rejected(
                    operation: $operation,
                    requestId: $requestId,
                    message: 'The inference request exceeds the configured size limit.',
                );
            }
        }

        for (
            $attempt = 1;
            $attempt <= $this->config->retryAttempts;
            $attempt++
        ) {
            try {
                $request = $this->request($requestId);
                $response = $method === 'GET'
                    ? $request->get($endpoint)
                    : $request->post($endpoint, $payload);
            } catch (ConnectionException $exception) {
                $this->recordFailure(
                    requestId: $requestId,
                    operation: $operation,
                    endpoint: $endpoint,
                    attempt: $attempt,
                    category: 'connection',
                    exception: $exception,
                );

                if ($attempt < $this->config->retryAttempts) {
                    $this->pause();

                    continue;
                }

                return AiInferenceResult::unavailable(
                    operation: $operation,
                    requestId: $requestId,
                    message: 'The FastAPI inference service could not be reached.',
                );
            } catch (Throwable $exception) {
                $this->recordFailure(
                    requestId: $requestId,
                    operation: $operation,
                    endpoint: $endpoint,
                    attempt: $attempt,
                    category: 'client',
                    exception: $exception,
                );

                return AiInferenceResult::unavailable(
                    operation: $operation,
                    requestId: $requestId,
                );
            }

            if (
                $this->isTransient($response)
                && $attempt < $this->config->retryAttempts
            ) {
                $this->pause();

                continue;
            }

            return $this->mapResponse(
                response: $response,
                operation: $operation,
                requestId: $requestId,
            );
        }

        return AiInferenceResult::unavailable(
            operation: $operation,
            requestId: $requestId,
        );
    }

    private function request(string $requestId): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config->baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($this->config->token)
            ->withHeaders([
                'X-Request-ID' => $requestId,
                'User-Agent' => $this->config->userAgent,
            ])
            ->connectTimeout($this->config->connectTimeoutSeconds)
            ->timeout($this->config->timeoutSeconds)
            ->withOptions([
                'verify' => $this->config->verifyTls,
            ]);
    }

    private function mapResponse(
        Response $response,
        string $operation,
        string $requestId,
    ): AiInferenceResult {
        if (strlen($response->body()) > $this->config->maximumResponseBytes) {
            return AiInferenceResult::invalidResponse(
                operation: $operation,
                requestId: $requestId,
                message: 'The inference response exceeded the configured size limit.',
                httpStatus: $response->status(),
            );
        }

        if ($response->status() === 401) {
            return AiInferenceResult::unavailable(
                operation: $operation,
                requestId: $requestId,
                message: 'The FastAPI service rejected internal authentication.',
                httpStatus: 401,
            );
        }

        if ($response->status() === 422) {
            return AiInferenceResult::rejected(
                operation: $operation,
                requestId: $requestId,
                message: 'The inference feature values did not satisfy the model contract.',
                httpStatus: 422,
            );
        }

        if (! $response->successful()) {
            return AiInferenceResult::unavailable(
                operation: $operation,
                requestId: $requestId,
                message: $this->isTransient($response)
                    ? 'The FastAPI inference service is temporarily unavailable.'
                    : 'The FastAPI inference request could not be completed.',
                httpStatus: $response->status(),
            );
        }

        try {
            $payload = $response->json();
        } catch (JsonException) {
            $payload = null;
        }

        if (! is_array($payload)) {
            return AiInferenceResult::invalidResponse(
                operation: $operation,
                requestId: $requestId,
                httpStatus: $response->status(),
            );
        }

        $normalized = match ($operation) {
            'models' => $this->validatedModels($payload, $requestId),
            'production_forecast' => $this->validatedForecast($payload, $requestId),
            'production_anomaly' => $this->validatedAnomaly($payload, $requestId),
            'maintenance_risk' => $this->validatedMaintenance($payload, $requestId),
            default => null,
        };

        if ($normalized === null) {
            return AiInferenceResult::invalidResponse(
                operation: $operation,
                requestId: $requestId,
                httpStatus: $response->status(),
            );
        }

        return AiInferenceResult::success(
            operation: $operation,
            requestId: $requestId,
            data: $normalized,
            httpStatus: $response->status(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function validatedModels(
        array $payload,
        string $requestId,
    ): ?array {
        $tasks = $payload['tasks'] ?? null;

        if (
            ($payload['status'] ?? null) !== 'ready'
            || ! $this->validUuid($payload['model_run_id'] ?? null)
            || ! $this->validUuid($payload['source_feature_run_id'] ?? null)
            || ($payload['data_classification'] ?? null) !== 'simulated_prototype'
            || ($payload['request_id'] ?? null) !== $requestId
            || ! is_array($tasks)
            || count($tasks) !== count(self::EXPECTED_TASKS)
        ) {
            return null;
        }

        foreach ($tasks as $task) {
            if (! is_string($task)) {
                return null;
            }
        }

        if (count(array_unique($tasks)) !== count($tasks)) {
            return null;
        }

        $normalizedTasks = array_values($tasks);
        sort($normalizedTasks);

        $expected = self::EXPECTED_TASKS;
        sort($expected);

        if ($normalizedTasks !== $expected) {
            return null;
        }

        return [
            'status' => 'ready',
            'model_run_id' => $payload['model_run_id'],
            'source_feature_run_id' => $payload['source_feature_run_id'],
            'tasks' => $tasks,
            'data_classification' => 'simulated_prototype',
            'request_id' => $requestId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function validatedForecast(
        array $payload,
        string $requestId,
    ): ?array {
        $prediction = $payload['predicted_good_quantity_next_day'] ?? null;
        $metadata = $this->validatedMetadata($payload['metadata'] ?? null);

        if (
            ($payload['status'] ?? null) !== 'ok'
            || ! $this->nonNegativeNumber($prediction)
            || ! $this->validDateString($payload['prediction_date'] ?? null)
            || $metadata === null
            || ($payload['request_id'] ?? null) !== $requestId
        ) {
            return null;
        }

        return [
            'status' => 'ok',
            'predicted_good_quantity_next_day' => (float) $prediction,
            'prediction_date' => $payload['prediction_date'],
            'metadata' => $metadata,
            'request_id' => $requestId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function validatedAnomaly(
        array $payload,
        string $requestId,
    ): ?array {
        $score = $payload['anomaly_score'] ?? null;
        $threshold = $payload['threshold'] ?? null;
        $metadata = $this->validatedMetadata($payload['metadata'] ?? null);

        if (
            ($payload['status'] ?? null) !== 'ok'
            || ! $this->finiteNumber($score)
            || ! $this->finiteNumber($threshold)
            || ! is_bool($payload['is_anomaly'] ?? null)
            || ! $this->validDateString($payload['event_time_utc'] ?? null)
            || $metadata === null
            || ($payload['request_id'] ?? null) !== $requestId
        ) {
            return null;
        }

        return [
            'status' => 'ok',
            'anomaly_score' => (float) $score,
            'threshold' => (float) $threshold,
            'is_anomaly' => $payload['is_anomaly'],
            'event_time_utc' => $payload['event_time_utc'],
            'metadata' => $metadata,
            'request_id' => $requestId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function validatedMaintenance(
        array $payload,
        string $requestId,
    ): ?array {
        $probability = $payload['failure_probability_next_7d'] ?? null;
        $downtime = $payload[
            'predicted_unplanned_downtime_minutes_next_7d'
        ] ?? null;
        $priority = $payload['priority'] ?? null;
        $metadata = $this->validatedMetadata($payload['metadata'] ?? null);

        if (
            ($payload['status'] ?? null) !== 'ok'
            || ! $this->finiteNumber($probability)
            || (float) $probability < 0
            || (float) $probability > 1
            || ! $this->nonNegativeNumber($downtime)
            || ! in_array(
                $priority,
                ['low', 'medium', 'high', 'critical'],
                true,
            )
            || ! $this->validDateString($payload['prediction_date'] ?? null)
            || $metadata === null
            || ($payload['request_id'] ?? null) !== $requestId
        ) {
            return null;
        }

        return [
            'status' => 'ok',
            'failure_probability_next_7d' => (float) $probability,
            'predicted_unplanned_downtime_minutes_next_7d' => (float) $downtime,
            'priority' => $priority,
            'prediction_date' => $payload['prediction_date'],
            'metadata' => $metadata,
            'request_id' => $requestId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validatedMetadata(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $limitations = $value['limitations'] ?? null;

        if (
            ! $this->validUuid($value['model_run_id'] ?? null)
            || ! $this->validUuid($value['source_feature_run_id'] ?? null)
            || ! is_string($value['model_name'] ?? null)
            || trim($value['model_name']) === ''
            || strlen($value['model_name']) > 200
            || ($value['data_classification'] ?? null) !== 'simulated_prototype'
            || ! is_array($limitations)
            || $limitations === []
            || count($limitations) > 20
        ) {
            return null;
        }

        foreach ($limitations as $limitation) {
            if (
                ! is_string($limitation)
                || trim($limitation) === ''
                || strlen($limitation) > 1000
            ) {
                return null;
            }
        }

        return [
            'model_run_id' => $value['model_run_id'],
            'source_feature_run_id' => $value['source_feature_run_id'],
            'model_name' => $value['model_name'],
            'data_classification' => 'simulated_prototype',
            'limitations' => array_values($limitations),
        ];
    }

    private function validRequestId(string $value): bool
    {
        return $value !== ''
            && strlen($value) <= 100
            && preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1;
    }

    private function validUuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }

    private function validDateString(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && strlen($value) <= 40
            && preg_match('/^[0-9T:+\-.Z]+$/', $value) === 1;
    }

    private function finiteNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value))
            && is_finite((float) $value);
    }

    private function nonNegativeNumber(mixed $value): bool
    {
        return $this->finiteNumber($value) && (float) $value >= 0;
    }

    private function isTransient(Response $response): bool
    {
        return in_array($response->status(), [408, 425, 429], true)
            || $response->serverError();
    }

    private function pause(): void
    {
        if ($this->config->retryDelayMilliseconds > 0) {
            usleep($this->config->retryDelayMilliseconds * 1000);
        }
    }

    private function recordFailure(
        string $requestId,
        string $operation,
        string $endpoint,
        int $attempt,
        string $category,
        Throwable $exception,
    ): void {
        try {
            Log::channel($this->config->logChannel)->warning(
                'FastAPI inference request failed.',
                [
                    'request_id' => $requestId,
                    'operation' => $operation,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'category' => $category,
                    'exception_type' => $exception::class,
                ],
            );
        } catch (Throwable) {
            // Logging failure must not break the safe client boundary.
        }
    }
}
