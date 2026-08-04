<?php

namespace App\Services\AI;

use App\Contracts\AI\AiServiceClientInterface;
use App\DTOs\AI\AiServiceConfig;
use App\DTOs\AI\AiServiceHealth;
use App\DTOs\AI\Analytics\AnalyticsContractValidationResult;
use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;
use App\Enums\AI\AiServiceHealthStatus;
use App\Enums\AI\AnalyticsContractValidationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

final readonly class FastApiAiServiceClient implements
    AiServiceClientInterface
{
    public function __construct(
        private Factory $http,
        private AiServiceConfig $config
    ) {
    }

    public function health(
        string $requestId
    ): AiServiceHealth {
        $startedAt = microtime(true);
        $lastResponse = null;

        for (
            $attempt = 1;
            $attempt <= $this->config->retryAttempts;
            $attempt++
        ) {
            try {
                $lastResponse = $this
                    ->request($requestId)
                    ->get(
                        $this->config
                            ->healthEndpoint
                    );
            } catch (ConnectionException $exception) {
                $this->recordFailure(
                    requestId: $requestId,
                    category: 'connection',
                    attempt: $attempt,
                    endpoint:
                        $this->config
                            ->healthEndpoint,
                    exception: $exception
                );

                if (
                    $attempt
                    < $this->config
                        ->retryAttempts
                ) {
                    $this->pause();
                    continue;
                }

                return AiServiceHealth::unavailable(
                    requestId: $requestId,
                    message:
                        'The FastAPI service could not be reached.'
                );
            } catch (Throwable $exception) {
                $this->recordFailure(
                    requestId: $requestId,
                    category: 'client',
                    attempt: $attempt,
                    endpoint:
                        $this->config
                            ->healthEndpoint,
                    exception: $exception
                );

                return AiServiceHealth::unavailable(
                    requestId: $requestId
                );
            }

            if (
                $this->isTransient(
                    $lastResponse
                )
                && $attempt
                    < $this->config
                        ->retryAttempts
            ) {
                $this->pause();
                continue;
            }

            return $this->mapHealthResponse(
                response: $lastResponse,
                requestId: $requestId,
                latencyMilliseconds:
                    $this->latency(
                        $startedAt
                    )
            );
        }

        return AiServiceHealth::unavailable(
            requestId: $requestId
        );
    }

    public function validateAnalyticsContract(
        AnalyticsSnapshotContract $contract,
        string $requestId
    ): AnalyticsContractValidationResult {
        try {
            $payload = $contract->toArray();

            $encodedPayload = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException) {
            return AnalyticsContractValidationResult
                ::rejected(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId,
                    message:
                        'The analytics snapshot could not be encoded safely.'
                );
        }

        if (
            strlen($encodedPayload)
            > $this->config
                ->maximumRequestBytes
        ) {
            return AnalyticsContractValidationResult
                ::rejected(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId,
                    message:
                        'The analytics snapshot exceeds the configured request-size limit.'
                );
        }

        $startedAt = microtime(true);
        $lastResponse = null;

        for (
            $attempt = 1;
            $attempt <= $this->config->retryAttempts;
            $attempt++
        ) {
            try {
                $lastResponse = $this
                    ->request($requestId)
                    ->withHeaders([
                        'Idempotency-Key' =>
                            $contract
                                ->snapshotId,

                        'X-Analytics-Contract-Version' =>
                            AnalyticsSnapshotContract
                                ::CONTRACT_VERSION,
                    ])
                    ->post(
                        $this->config
                            ->analyticsContractEndpoint,
                        $payload
                    );
            } catch (ConnectionException $exception) {
                $this->recordFailure(
                    requestId: $requestId,
                    category: 'connection',
                    attempt: $attempt,
                    endpoint:
                        $this->config
                            ->analyticsContractEndpoint,
                    exception: $exception
                );

                if (
                    $attempt
                    < $this->config
                        ->retryAttempts
                ) {
                    $this->pause();
                    continue;
                }

                return AnalyticsContractValidationResult
                    ::unavailable(
                        snapshotId:
                            $contract->snapshotId,
                        requestId: $requestId,
                        message:
                            'The FastAPI analytics contract endpoint could not be reached.'
                    );
            } catch (Throwable $exception) {
                $this->recordFailure(
                    requestId: $requestId,
                    category: 'client',
                    attempt: $attempt,
                    endpoint:
                        $this->config
                            ->analyticsContractEndpoint,
                    exception: $exception
                );

                return AnalyticsContractValidationResult
                    ::unavailable(
                        snapshotId:
                            $contract->snapshotId,
                        requestId: $requestId
                    );
            }

            if (
                $this->isTransient(
                    $lastResponse
                )
                && $attempt
                    < $this->config
                        ->retryAttempts
            ) {
                $this->pause();
                continue;
            }

            return $this->mapAnalyticsResponse(
                response: $lastResponse,
                contract: $contract,
                requestId: $requestId,
                latencyMilliseconds:
                    $this->latency(
                        $startedAt
                    )
            );
        }

        return AnalyticsContractValidationResult
            ::unavailable(
                snapshotId:
                    $contract->snapshotId,
                requestId: $requestId
            );
    }

    private function request(
        string $requestId
    ): PendingRequest {
        return $this->http
            ->baseUrl(
                $this->config->baseUrl
            )
            ->acceptJson()
            ->asJson()
            ->withToken(
                $this->config->token
            )
            ->withHeaders([
                'X-Request-ID' => $requestId,
                'User-Agent' =>
                    $this->config
                        ->userAgent,
            ])
            ->connectTimeout(
                $this->config
                    ->connectTimeoutSeconds
            )
            ->timeout(
                $this->config
                    ->timeoutSeconds
            )
            ->withOptions([
                'verify' =>
                    $this->config
                        ->verifyTls,
            ]);
    }

    private function mapHealthResponse(
        Response $response,
        string $requestId,
        int $latencyMilliseconds
    ): AiServiceHealth {
        if (
            strlen($response->body())
            > $this->config
                ->maximumResponseBytes
        ) {
            return new AiServiceHealth(
                status:
                    AiServiceHealthStatus
                        ::Degraded,
                checkedAt:
                    CarbonImmutable::now(),
                latencyMilliseconds:
                    $latencyMilliseconds,
                requestId: $requestId,
                message:
                    'The FastAPI health response exceeded the allowed size.'
            );
        }

        if ($response->status() === 401) {
            return new AiServiceHealth(
                status:
                    AiServiceHealthStatus
                        ::Unavailable,
                checkedAt:
                    CarbonImmutable::now(),
                latencyMilliseconds:
                    $latencyMilliseconds,
                requestId: $requestId,
                message:
                    'The FastAPI service rejected internal authentication.'
            );
        }

        if (! $response->successful()) {
            return new AiServiceHealth(
                status:
                    $this->isTransient(
                        $response
                    )
                        ? AiServiceHealthStatus
                            ::Degraded
                        : AiServiceHealthStatus
                            ::Unavailable,
                checkedAt:
                    CarbonImmutable::now(),
                latencyMilliseconds:
                    $latencyMilliseconds,
                requestId: $requestId,
                message:
                    'The FastAPI health endpoint returned an unavailable state.'
            );
        }

        try {
            $payload = $response->json();
        } catch (JsonException) {
            $payload = null;
        }

        if (! is_array($payload)) {
            return $this->invalidHealthContract(
                $requestId,
                $latencyMilliseconds
            );
        }

        $status =
            $payload['status']
            ?? null;

        $serviceVersion =
            $payload['version']
            ?? null;

        $apiVersion =
            $payload['api_version']
            ?? null;

        $responseRequestId =
            $payload['request_id']
            ?? null;

        if (
            $status !== 'ready'
            || ! is_string(
                $serviceVersion
            )
            || ! preg_match(
                '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/',
                $serviceVersion
            )
            || ! is_string(
                $apiVersion
            )
            || ! preg_match(
                '/^v[1-9][0-9]*$/',
                $apiVersion
            )
            || ! is_string(
                $responseRequestId
            )
            || $responseRequestId
                !== $requestId
        ) {
            return $this->invalidHealthContract(
                $requestId,
                $latencyMilliseconds
            );
        }

        $ollama = $this->validatedOllamaDependency(
            $payload['dependencies']
                ?? null
        );

        if ($ollama === null) {
            return $this->invalidHealthContract(
                $requestId,
                $latencyMilliseconds
            );
        }

        $ollamaStatus = $ollama['status'];
        $ollamaModel = $ollama['model'];

        return new AiServiceHealth(
            status:
                $ollamaStatus === 'available'
                    ? AiServiceHealthStatus
                        ::Available
                    : AiServiceHealthStatus
                        ::Degraded,
            checkedAt:
                CarbonImmutable::now(),
            latencyMilliseconds:
                $latencyMilliseconds,
            serviceVersion:
                $serviceVersion,
            apiVersion:
                $apiVersion,
            requestId:
                $responseRequestId,
            message:
                $ollamaStatus === 'available'
                    ? 'The FastAPI service and configured Ollama model are available.'
                    : (
                        $ollamaStatus === 'disabled'
                            ? 'The FastAPI inference service is ready, but Ollama is disabled.'
                            : 'The FastAPI inference service is ready, but the configured Ollama dependency is degraded.'
                    )
        );
    }

    private function mapAnalyticsResponse(
        Response $response,
        AnalyticsSnapshotContract $contract,
        string $requestId,
        int $latencyMilliseconds
    ): AnalyticsContractValidationResult {
        if (
            strlen($response->body())
            > $this->config
                ->maximumResponseBytes
        ) {
            return AnalyticsContractValidationResult
                ::rejected(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId,
                    message:
                        'The FastAPI analytics response exceeded the allowed size.'
                );
        }

        if ($response->status() === 401) {
            return AnalyticsContractValidationResult
                ::unavailable(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId,
                    message:
                        'The FastAPI service rejected internal authentication.'
                );
        }

        if (
            in_array(
                $response->status(),
                [
                    409,
                    422,
                ],
                true
            )
        ) {
            return AnalyticsContractValidationResult
                ::rejected(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId
                );
        }

        if (! $response->successful()) {
            return AnalyticsContractValidationResult
                ::unavailable(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId,
                    message:
                        'The FastAPI analytics contract endpoint returned an unavailable state.'
                );
        }

        try {
            $payload = $response->json();
        } catch (JsonException) {
            $payload = null;
        }

        if (! is_array($payload)) {
            return $this->invalidAnalyticsContract(
                contract: $contract,
                requestId: $requestId
            );
        }

        $acceptedSections =
            $payload['accepted_sections']
            ?? null;

        $responseRequestId =
            $payload['request_id']
            ?? null;

        $receivedAt =
            $payload['received_at']
            ?? null;

        if (
            ($payload['status'] ?? null)
                !== 'accepted'
            || ($payload['contract_name'] ?? null)
                !== AnalyticsSnapshotContract
                    ::CONTRACT_NAME
            || ($payload['contract_version'] ?? null)
                !== AnalyticsSnapshotContract
                    ::CONTRACT_VERSION
            || ($payload['snapshot_id'] ?? null)
                !== $contract->snapshotId
            || ! is_array($acceptedSections)
            || array_values($acceptedSections)
                !== $contract->sectionNames()
            || ! is_string($responseRequestId)
            || $responseRequestId
                !== $requestId
            || ! is_string($receivedAt)
        ) {
            return $this->invalidAnalyticsContract(
                contract: $contract,
                requestId: $requestId
            );
        }

        try {
            $checkedAt =
                CarbonImmutable::parse(
                    $receivedAt
                );
        } catch (Throwable) {
            return $this->invalidAnalyticsContract(
                contract: $contract,
                requestId: $requestId
            );
        }

        return new AnalyticsContractValidationResult(
            status:
                AnalyticsContractValidationStatus
                    ::Accepted,
            checkedAt: $checkedAt,
            snapshotId:
                $contract->snapshotId,
            acceptedSections:
                array_values(
                    $acceptedSections
                ),
            requestId:
                $responseRequestId,
            message:
                'The versioned analytics snapshot contract was accepted.'
        );
    }

    /**
     * @return array{status:string,model:?string}|null
     */
    private function validatedOllamaDependency(
        mixed $dependencies
    ): ?array {
        if (! is_array($dependencies)) {
            return null;
        }

        $ollama = null;

        foreach ($dependencies as $dependency) {
            if (
                ! is_array($dependency)
                || ($dependency['name'] ?? null)
                    !== 'ollama'
            ) {
                continue;
            }

            if ($ollama !== null) {
                return null;
            }

            $ollama = $dependency;
        }

        if ($ollama === null) {
            return null;
        }

        $status = $ollama['status'] ?? null;
        $required = $ollama['required'] ?? null;
        $model = $ollama['model'] ?? null;
        $latency = $ollama['latency_ms'] ?? null;
        $message = $ollama['message'] ?? null;

        if (
            ! is_string($status)
            || ! in_array(
                $status,
                [
                    'available',
                    'degraded',
                    'disabled',
                ],
                true
            )
            || ! is_bool($required)
            || $required
            || (
                $model !== null
                && (
                    ! is_string($model)
                    || trim($model) === ''
                    || strlen($model) > 164
                    || preg_match(
                        '/[\x00-\x1F\x7F]/',
                        $model
                    ) === 1
                )
            )
            || (
                $latency !== null
                && (
                    ! is_int($latency)
                    || $latency < 0
                )
            )
            || ! is_string($message)
            || trim($message) === ''
            || strlen($message) > 300
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $message
            ) === 1
        ) {
            return null;
        }

        if (
            $status !== 'disabled'
            && $model === null
        ) {
            return null;
        }

        return [
            'status' => $status,
            'model' =>
                is_string($model)
                    ? trim($model)
                    : null,
        ];
    }

    private function invalidHealthContract(
        string $requestId,
        int $latencyMilliseconds
    ): AiServiceHealth {
        return new AiServiceHealth(
            status:
                AiServiceHealthStatus
                    ::Degraded,
            checkedAt:
                CarbonImmutable::now(),
            latencyMilliseconds:
                $latencyMilliseconds,
            requestId: $requestId,
            message:
                'The FastAPI health response did not match the expected contract.'
        );
    }

    private function invalidAnalyticsContract(
        AnalyticsSnapshotContract $contract,
        string $requestId
    ): AnalyticsContractValidationResult {
        return AnalyticsContractValidationResult
            ::rejected(
                snapshotId:
                    $contract->snapshotId,
                requestId: $requestId,
                message:
                    'The FastAPI analytics response did not match the expected contract.'
            );
    }

    private function isTransient(
        Response $response
    ): bool {
        return in_array(
            $response->status(),
            [
                429,
                502,
                503,
                504,
            ],
            true
        );
    }

    private function pause(): void
    {
        if (
            $this->config
                ->retryDelayMilliseconds
            <= 0
        ) {
            return;
        }

        usleep(
            $this->config
                ->retryDelayMilliseconds
            * 1000
        );
    }

    private function latency(
        float $startedAt
    ): int {
        return max(
            0,
            (int) round(
                (
                    microtime(true)
                    - $startedAt
                )
                * 1000
            )
        );
    }

    private function recordFailure(
        string $requestId,
        string $category,
        int $attempt,
        string $endpoint,
        Throwable $exception
    ): void {
        /*
         * Never log bearer tokens, headers, request/response
         * bodies, analytics values, or raw exception messages.
         */
        Log::channel(
            $this->config
                ->logChannel
        )->warning(
            'AI service request failed safely.',
            [
                'request_id' => $requestId,
                'category' => $category,
                'attempt' => $attempt,
                'exception_type' =>
                    $exception::class,
                'endpoint' => $endpoint,
            ]
        );
    }
}
