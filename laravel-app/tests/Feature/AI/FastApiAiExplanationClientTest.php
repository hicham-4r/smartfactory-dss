<?php

namespace Tests\Feature\AI;

use App\DTOs\AI\Explanations\AiExplanationConfig;
use App\Enums\AI\AiExplanationStatus;
use App\Services\AI\Explanations\FastApiAiExplanationClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FastApiAiExplanationClientTest extends TestCase
{
    public function test_successful_response_is_authenticated_and_strictly_validated(): void
    {
        $requestId = 'laravel-ai-explanation-001';
        $payload = $this->requestPayload();

        Http::fake(
            function (Request $request) use (
                $requestId,
                $payload,
            ) {
                self::assertSame('POST', $request->method());
                self::assertTrue(
                    $request->hasHeader(
                        'Authorization',
                        'Bearer '.str_repeat('t', 64),
                    ),
                );
                self::assertTrue(
                    $request->hasHeader(
                        'X-Request-ID',
                        $requestId,
                    ),
                );
                self::assertSame(
                    $payload['explanation_id'],
                    $request['explanation_id'],
                );

                return Http::response(
                    $this->responsePayload(
                        requestId: $requestId,
                        explanationId: $payload['explanation_id'],
                    ),
                );
            },
        );

        $result = $this->client()->generate(
            payload: $payload,
            requestId: $requestId,
        );

        self::assertTrue($result->succeeded());
        self::assertSame(
            'The verified forecast is 995 L.',
            $result->data['narrative']['summary'],
        );
        Http::assertSentCount(1);
    }

    public function test_unknown_response_fields_are_rejected_without_exposing_raw_output(): void
    {
        $requestId = 'laravel-ai-explanation-002';
        $payload = $this->requestPayload();
        $response = $this->responsePayload(
            requestId: $requestId,
            explanationId: $payload['explanation_id'],
        );
        $response['raw_prompt'] = 'SECRET';

        Http::fake([
            '*' => Http::response($response),
        ]);

        $result = $this->client()->generate(
            payload: $payload,
            requestId: $requestId,
        );

        self::assertSame(
            AiExplanationStatus::InvalidResponse,
            $result->status,
        );
        self::assertStringNotContainsString(
            'SECRET',
            (string) $result->message,
        );
    }

    public function test_rate_limit_and_ollama_unavailability_are_mapped_safely(): void
    {
        $payload = $this->requestPayload();

        Http::fakeSequence()
            ->push(
                ['error' => ['code' => 'explanation_rate_limited']],
                429,
                ['Retry-After' => '7'],
            )
            ->push(
                ['error' => ['code' => 'ollama_unavailable']],
                503,
            );

        $limited = $this->client()->generate(
            payload: $payload,
            requestId: 'laravel-ai-explanation-003',
        );
        $unavailable = $this->client()->generate(
            payload: $payload,
            requestId: 'laravel-ai-explanation-004',
        );

        self::assertSame(
            AiExplanationStatus::RateLimited,
            $limited->status,
        );
        self::assertSame(7, $limited->retryAfterSeconds);
        self::assertSame(
            AiExplanationStatus::Unavailable,
            $unavailable->status,
        );
        Http::assertSentCount(2);
    }

    private function client(): FastApiAiExplanationClient
    {
        return new FastApiAiExplanationClient(
            http: app(Factory::class),
            config: new AiExplanationConfig(
                baseUrl: 'http://127.0.0.1:8001',
                token: str_repeat('t', 64),
                verifyTls: false,
                endpoint: '/internal/v1/explanations/generate',
                connectTimeoutSeconds: 2,
                timeoutSeconds: 90,
                maximumRequestBytes: 65536,
                maximumResponseBytes: 65536,
                userAgent: 'SmartFactory-DSS/1.0',
                logChannel: 'stack',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(): array
    {
        return [
            'contract_name' => 'smartfactory.llm.explanation',
            'contract_version' => 'v1',
            'explanation_id' => '33333333-3333-4333-8333-333333333333',
            'requested_at' => '2026-08-04T01:30:00+00:00',
            'role' => 'production_manager',
            'language' => 'en',
            'facts' => [
                'explanation_type' => 'production_forecast',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(
        string $requestId,
        string $explanationId,
    ): array {
        return [
            'status' => 'generated',
            'contract_name' => 'smartfactory.llm.explanation',
            'contract_version' => 'v1',
            'explanation_id' => $explanationId,
            'explanation_type' => 'production_forecast',
            'role' => 'production_manager',
            'language' => 'en',
            'data_classification' => 'simulated_prototype',
            'narrative' => [
                'summary' => 'The verified forecast is 995 L.',
                'observations' => [
                    'The supplied seven-day mean is 980 L.',
                ],
                'suggested_human_checks' => [
                    'Review validated downtime records.',
                ],
                'limitations' => [
                    'Simulated-prototype data only.',
                ],
                'referenced_fact_keys' => [
                    'facts.result.predicted_good_quantity_next_day',
                ],
            ],
            'request_id' => $requestId,
        ];
    }
}
