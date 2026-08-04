<?php

namespace App\Services\AI\Explanations;

use App\Contracts\AI\Explanations\AiExplanationClientInterface;
use App\DTOs\AI\Explanations\AiExplanationConfig;
use App\DTOs\AI\Explanations\AiExplanationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final readonly class FastApiAiExplanationClient implements
    AiExplanationClientInterface
{
    private const TOP_LEVEL_KEYS = [
        'status',
        'contract_name',
        'contract_version',
        'explanation_id',
        'explanation_type',
        'role',
        'language',
        'data_classification',
        'narrative',
        'request_id',
    ];

    private const NARRATIVE_KEYS = [
        'summary',
        'observations',
        'suggested_human_checks',
        'limitations',
        'referenced_fact_keys',
    ];

    public function __construct(
        private Factory $http,
        private AiExplanationConfig $config,
    ) {}

    public function generate(
        array $payload,
        string $requestId,
    ): AiExplanationResult {
        if (! $this->validRequestId($requestId)) {
            return AiExplanationResult::rejected(
                requestId: $requestId,
                message: 'The internal request identifier is invalid.',
            );
        }

        try {
            $encoded = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            return AiExplanationResult::rejected(
                requestId: $requestId,
                message: 'The explanation request could not be encoded safely.',
            );
        }

        if (strlen($encoded) > $this->config->maximumRequestBytes) {
            return AiExplanationResult::rejected(
                requestId: $requestId,
                message: 'The explanation request exceeds the configured size limit.',
            );
        }

        try {
            $response = $this->http
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
                ])
                ->post($this->config->endpoint, $payload);
        } catch (ConnectionException $exception) {
            $this->recordFailure(
                requestId: $requestId,
                category: 'connection',
                exception: $exception,
            );

            return AiExplanationResult::unavailable(
                requestId: $requestId,
                message: 'The guarded explanation service could not be reached.',
            );
        } catch (Throwable $exception) {
            $this->recordFailure(
                requestId: $requestId,
                category: 'client',
                exception: $exception,
            );

            return AiExplanationResult::unavailable(
                requestId: $requestId,
            );
        }

        return $this->mapResponse(
            response: $response,
            requestPayload: $payload,
            requestId: $requestId,
        );
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     */
    private function mapResponse(
        Response $response,
        array $requestPayload,
        string $requestId,
    ): AiExplanationResult {
        if (strlen($response->body()) > $this->config->maximumResponseBytes) {
            return AiExplanationResult::invalidResponse(
                requestId: $requestId,
                message: 'The explanation response exceeded the configured size limit.',
                httpStatus: $response->status(),
            );
        }

        if ($response->status() === 401) {
            return AiExplanationResult::unavailable(
                requestId: $requestId,
                message: 'FastAPI rejected the internal explanation authentication.',
                httpStatus: 401,
            );
        }

        if ($response->status() === 422) {
            return AiExplanationResult::rejected(
                requestId: $requestId,
                httpStatus: 422,
            );
        }

        if ($response->status() === 429) {
            return AiExplanationResult::rateLimited(
                requestId: $requestId,
                retryAfterSeconds: $this->retryAfter($response),
            );
        }

        if ($response->status() === 502) {
            return AiExplanationResult::invalidResponse(
                requestId: $requestId,
                message: 'The generated explanation was rejected by the guarded validation boundary.',
                httpStatus: 502,
            );
        }

        if ($response->status() === 503) {
            return AiExplanationResult::unavailable(
                requestId: $requestId,
                message: 'The local Ollama explanation dependency is temporarily unavailable.',
                httpStatus: 503,
            );
        }

        if (! $response->successful()) {
            return AiExplanationResult::unavailable(
                requestId: $requestId,
                message: 'The guarded explanation request could not be completed.',
                httpStatus: $response->status(),
            );
        }

        try {
            $payload = $response->json();
        } catch (JsonException) {
            $payload = null;
        }

        if (
            ! is_array($payload)
            || ! $this->hasExactKeys($payload, self::TOP_LEVEL_KEYS)
            || ! $this->validSuccessfulPayload(
                $payload,
                $requestPayload,
                $requestId,
            )
        ) {
            return AiExplanationResult::invalidResponse(
                requestId: $requestId,
                httpStatus: $response->status(),
            );
        }

        return AiExplanationResult::success(
            requestId: $requestId,
            data: $payload,
            httpStatus: $response->status(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestPayload
     */
    private function validSuccessfulPayload(
        array $payload,
        array $requestPayload,
        string $requestId,
    ): bool {
        $narrative = $payload['narrative'] ?? null;

        return ($payload['status'] ?? null) === 'generated'
            && ($payload['contract_name'] ?? null)
                === 'smartfactory.llm.explanation'
            && ($payload['contract_version'] ?? null) === 'v1'
            && ($payload['explanation_id'] ?? null)
                === ($requestPayload['explanation_id'] ?? null)
            && ($payload['explanation_type'] ?? null)
                === ($requestPayload['facts']['explanation_type'] ?? null)
            && ($payload['role'] ?? null)
                === ($requestPayload['role'] ?? null)
            && ($payload['language'] ?? null)
                === ($requestPayload['language'] ?? null)
            && ($payload['data_classification'] ?? null)
                === 'simulated_prototype'
            && ($payload['request_id'] ?? null) === $requestId
            && is_array($narrative)
            && $this->hasExactKeys($narrative, self::NARRATIVE_KEYS)
            && $this->validNarrative($narrative);
    }

    /**
     * @param  array<string, mixed>  $narrative
     */
    private function validNarrative(array $narrative): bool
    {
        return $this->validString(
            $narrative['summary'] ?? null,
            1,
            600,
        )
            && $this->validStringList(
                $narrative['observations'] ?? null,
                1,
                5,
                300,
            )
            && $this->validStringList(
                $narrative['suggested_human_checks'] ?? null,
                1,
                5,
                300,
            )
            && $this->validStringList(
                $narrative['limitations'] ?? null,
                1,
                12,
                400,
            )
            && $this->validFactKeys(
                $narrative['referenced_fact_keys'] ?? null,
            );
    }

    private function validFactKeys(mixed $value): bool
    {
        if (
            ! is_array($value)
            || $value === []
            || count($value) > 40
        ) {
            return false;
        }

        $normalized = [];

        foreach ($value as $item) {
            if (
                ! is_string($item)
                || preg_match(
                    '/^facts\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/',
                    $item,
                ) !== 1
                || strlen($item) > 160
            ) {
                return false;
            }

            $normalized[] = $item;
        }

        return count($normalized) === count(array_unique($normalized));
    }

    private function validStringList(
        mixed $value,
        int $minimumItems,
        int $maximumItems,
        int $maximumLength,
    ): bool {
        if (
            ! is_array($value)
            || count($value) < $minimumItems
            || count($value) > $maximumItems
        ) {
            return false;
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! $this->validString($item, 1, $maximumLength)) {
                return false;
            }

            $normalized[] = mb_strtolower(trim($item));
        }

        return count($normalized) === count(array_unique($normalized));
    }

    private function validString(
        mixed $value,
        int $minimumLength,
        int $maximumLength,
    ): bool {
        if (! is_string($value)) {
            return false;
        }

        $length = mb_strlen(trim($value));

        return $length >= $minimumLength
            && $length <= $maximumLength
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $expected
     */
    private function hasExactKeys(
        array $payload,
        array $expected,
    ): bool {
        $actual = array_keys($payload);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }

    private function validRequestId(string $requestId): bool
    {
        return $requestId !== ''
            && strlen($requestId) <= 100
            && preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) === 1;
    }

    private function retryAfter(Response $response): int
    {
        $raw = $response->header('Retry-After');

        if (
            is_string($raw)
            && ctype_digit($raw)
            && (int) $raw > 0
            && (int) $raw <= 3600
        ) {
            return (int) $raw;
        }

        return 5;
    }

    private function recordFailure(
        string $requestId,
        string $category,
        Throwable $exception,
    ): void {
        try {
            Log::channel($this->config->logChannel)->warning(
                'FastAPI explanation request failed.',
                [
                    'request_id' => $requestId,
                    'endpoint' => $this->config->endpoint,
                    'category' => $category,
                    'exception_type' => $exception::class,
                ],
            );
        } catch (Throwable) {
            // Logging failure must not break the safe client boundary.
        }
    }
}
