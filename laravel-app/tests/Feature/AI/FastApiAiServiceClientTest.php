<?php

namespace Tests\Feature\AI;

use App\DTOs\AI\AiServiceConfig;
use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;
use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\ProductionKpiUnitSummary;
use App\Enums\AI\AiServiceHealthStatus;
use App\Enums\AI\AnalyticsContractValidationStatus;
use App\Services\AI\FastApiAiServiceClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FastApiAiServiceClientTest extends TestCase
{
    public function test_health_request_uses_authentication_timeouts_and_request_id(): void
    {
        $requestId = 'laravel-ai-health-001';

        Http::fake(
            function (
                Request $request
            ) use (
                $requestId
            ) {
                self::assertTrue(
                    $request->hasHeader(
                        'Authorization',
                        'Bearer '
                        .str_repeat('t', 64)
                    )
                );

                self::assertTrue(
                    $request->hasHeader(
                        'X-Request-ID',
                        $requestId
                    )
                );

                self::assertTrue(
                    $request->hasHeader(
                        'User-Agent',
                        'SmartFactory-DSS/1.0'
                    )
                );

                return Http::response([
                    'status' => 'ready',
                    'service' =>
                        'SmartFactory DSS AI Service',
                    'version' => '0.1.0',
                    'api_version' => 'v1',
                    'checked_at' =>
                        now()->utc()->toIso8601String(),
                    'dependencies' => [
                        [
                            'name' => 'ollama',
                            'status' => 'available',
                            'required' => false,
                            'model' => 'llama3:8b',
                            'latency_ms' => 12,
                            'message' =>
                                'The configured local Ollama model is available.',
                        ],
                    ],
                    'request_id' => $requestId,
                ]);
            }
        );

        $health = $this
            ->client()
            ->health($requestId);

        self::assertSame(
            AiServiceHealthStatus::Available,
            $health->status
        );

        self::assertSame(
            '0.1.0',
            $health->serviceVersion
        );

        self::assertSame(
            'v1',
            $health->apiVersion
        );

        Http::assertSentCount(1);
    }

    public function test_transient_get_failure_is_retried_safely(): void
    {
        $requestId = 'laravel-ai-health-002';

        Http::fakeSequence()
            ->push(
                [
                    'error' => [
                        'code' => 'not_ready',
                    ],
                ],
                503
            )
            ->push([
                'status' => 'ready',
                'service' =>
                    'SmartFactory DSS AI Service',
                'version' => '0.1.0',
                'api_version' => 'v1',
                'checked_at' =>
                    now()->utc()->toIso8601String(),
                'dependencies' => [
                    [
                        'name' => 'ollama',
                        'status' => 'available',
                        'required' => false,
                        'model' => 'llama3:8b',
                        'latency_ms' => 12,
                        'message' =>
                            'The configured local Ollama model is available.',
                    ],
                ],
                'request_id' => $requestId,
            ]);

        $health = $this
            ->client()
            ->health($requestId);

        self::assertSame(
            AiServiceHealthStatus::Available,
            $health->status
        );

        Http::assertSentCount(2);
    }

    public function test_invalid_health_contract_degrades_without_exposing_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ready',
                'version' =>
                    'secret-invalid-value',
            ]),
        ]);

        $health = $this
            ->client()
            ->health(
                'laravel-ai-health-003'
            );

        self::assertSame(
            AiServiceHealthStatus::Degraded,
            $health->status
        );

        self::assertSame(
            'The FastAPI health response did not match the expected contract.',
            $health->message
        );
    }

    public function test_unavailable_service_does_not_throw_into_laravel(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'error' => [
                        'code' => 'unavailable',
                    ],
                ],
                503
            ),
        ]);

        $health = $this
            ->client()
            ->health(
                'laravel-ai-health-004'
            );

        self::assertSame(
            AiServiceHealthStatus::Degraded,
            $health->status
        );
    }

    public function test_degraded_ollama_dependency_does_not_hide_ready_inference(): void
    {
        Http::fake(
            function (
                Request $request
            ) {
                $requestId = $request
                    ->header(
                        'X-Request-ID'
                    )[0];

                return Http::response([
                    'status' => 'ready',
                    'service' =>
                        'SmartFactory DSS AI Service',
                    'version' => '0.1.0',
                    'api_version' => 'v1',
                    'checked_at' =>
                        now()->utc()->toIso8601String(),
                    'dependencies' => [
                        [
                            'name' => 'ollama',
                            'status' => 'degraded',
                            'required' => false,
                            'model' => 'llama3:8b',
                            'latency_ms' => null,
                            'message' =>
                                'The local Ollama service is unavailable.',
                        ],
                    ],
                    'request_id' => $requestId,
                ]);
            }
        );

        $health = $this
            ->client()
            ->health(
                'laravel-ai-health-ollama-degraded'
            );

        self::assertSame(
            AiServiceHealthStatus::Degraded,
            $health->status
        );

        self::assertSame(
            'The FastAPI inference service is ready, but the configured Ollama dependency is degraded.',
            $health->message
        );
    }

    public function test_malformed_ollama_dependency_degrades_safely(): void
    {
        Http::fake(
            function (
                Request $request
            ) {
                $requestId = $request
                    ->header(
                        'X-Request-ID'
                    )[0];

                return Http::response([
                    'status' => 'ready',
                    'service' =>
                        'SmartFactory DSS AI Service',
                    'version' => '0.1.0',
                    'api_version' => 'v1',
                    'checked_at' =>
                        now()->utc()->toIso8601String(),
                    'dependencies' => [
                        [
                            'name' => 'ollama',
                            'status' => 'available',
                            'required' => false,
                            'model' => null,
                            'latency_ms' => 1,
                            'message' => 'Invalid.',
                        ],
                    ],
                    'request_id' => $requestId,
                ]);
            }
        );

        $health = $this
            ->client()
            ->health(
                'laravel-ai-health-ollama-invalid'
            );

        self::assertSame(
            AiServiceHealthStatus::Degraded,
            $health->status
        );

        self::assertSame(
            'The FastAPI health response did not match the expected contract.',
            $health->message
        );
    }

    public function test_analytics_contract_uses_versioned_headers_and_sanitized_payload(): void
    {
        $requestId =
            'laravel-ai-contract-001';

        $contract = $this->contract();

        Http::fake(
            function (
                Request $request
            ) use (
                $requestId,
                $contract
            ) {
                self::assertSame(
                    'POST',
                    $request->method()
                );

                self::assertSame(
                    'http://127.0.0.1:8001'
                    .'/internal/v1/contracts'
                    .'/analytics/validate',
                    $request->url()
                );

                self::assertTrue(
                    $request->hasHeader(
                        'Authorization',
                        'Bearer '
                        .str_repeat('t', 64)
                    )
                );

                self::assertTrue(
                    $request->hasHeader(
                        'X-Request-ID',
                        $requestId
                    )
                );

                self::assertTrue(
                    $request->hasHeader(
                        'Idempotency-Key',
                        $contract->snapshotId
                    )
                );

                self::assertTrue(
                    $request->hasHeader(
                        'X-Analytics-Contract-Version',
                        'v1'
                    )
                );

                self::assertSame(
                    'simulated_prototype',
                    $request->data()[
                        'metadata'
                    ][
                        'data_classification'
                    ]
                );

                self::assertArrayNotHasKey(
                    'token',
                    $request->data()
                );

                return Http::response([
                    'status' => 'accepted',
                    'contract_name' =>
                        AnalyticsSnapshotContract
                            ::CONTRACT_NAME,
                    'contract_version' =>
                        AnalyticsSnapshotContract
                            ::CONTRACT_VERSION,
                    'snapshot_id' =>
                        $contract->snapshotId,
                    'accepted_sections' => [
                        'production_kpis',
                    ],
                    'received_at' =>
                        now()->utc()->toIso8601String(),
                    'request_id' => $requestId,
                ]);
            }
        );

        $result = $this
            ->client()
            ->validateAnalyticsContract(
                contract: $contract,
                requestId: $requestId
            );

        self::assertSame(
            AnalyticsContractValidationStatus
                ::Accepted,
            $result->status
        );

        self::assertSame(
            [
                'production_kpis',
            ],
            $result->acceptedSections
        );

        Http::assertSentCount(1);
    }

    public function test_transient_contract_failure_reuses_the_same_idempotency_key(): void
    {
        $requestId =
            'laravel-ai-contract-002';

        $contract = $this->contract();

        Http::fakeSequence()
            ->push(
                [
                    'error' => [
                        'code' => 'not_ready',
                    ],
                ],
                503
            )
            ->push([
                'status' => 'accepted',
                'contract_name' =>
                    AnalyticsSnapshotContract
                        ::CONTRACT_NAME,
                'contract_version' =>
                    AnalyticsSnapshotContract
                        ::CONTRACT_VERSION,
                'snapshot_id' =>
                    $contract->snapshotId,
                'accepted_sections' => [
                    'production_kpis',
                ],
                'received_at' =>
                    now()->utc()->toIso8601String(),
                'request_id' => $requestId,
            ]);

        $result = $this
            ->client()
            ->validateAnalyticsContract(
                contract: $contract,
                requestId: $requestId
            );

        self::assertTrue(
            $result->isAccepted()
        );

        Http::assertSentCount(2);

        Http::assertSent(
            fn (
                Request $request
            ): bool =>
                $request->hasHeader(
                    'Idempotency-Key',
                    $contract->snapshotId
                )
        );
    }

    public function test_fastapi_validation_rejection_is_failure_isolated(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'error' => [
                        'code' =>
                            'validation_error',
                    ],
                ],
                422
            ),
        ]);

        $result = $this
            ->client()
            ->validateAnalyticsContract(
                contract:
                    $this->contract(),
                requestId:
                    'laravel-ai-contract-003'
            );

        self::assertSame(
            AnalyticsContractValidationStatus
                ::Rejected,
            $result->status
        );
    }

    public function test_invalid_analytics_response_is_rejected_without_exposing_body(): void
    {
        $contract = $this->contract();

        Http::fake([
            '*' => Http::response([
                'status' => 'accepted',
                'snapshot_id' =>
                    '22222222-2222-4222-8222-222222222222',
                'secret' =>
                    'must-not-be-exposed',
            ]),
        ]);

        $result = $this
            ->client()
            ->validateAnalyticsContract(
                contract: $contract,
                requestId:
                    'laravel-ai-contract-004'
            );

        self::assertSame(
            AnalyticsContractValidationStatus
                ::Rejected,
            $result->status
        );

        self::assertSame(
            'The FastAPI analytics response did not match the expected contract.',
            $result->message
        );

        self::assertStringNotContainsString(
            'must-not-be-exposed',
            (string) $result->message
        );
    }

    public function test_oversized_analytics_request_is_rejected_before_http(): void
    {
        Http::fake();

        $result = $this
            ->client(
                maximumRequestBytes: 1024
            )
            ->validateAnalyticsContract(
                contract:
                    $this->contract(
                        quantityUnit:
                            str_repeat(
                                'L',
                                1500
                            )
                    ),
                requestId:
                    'laravel-ai-contract-005'
            );

        self::assertSame(
            AnalyticsContractValidationStatus
                ::Rejected,
            $result->status
        );

        Http::assertNothingSent();
    }

    private function client(
        int $maximumRequestBytes = 1048576
    ): FastApiAiServiceClient {
        return new FastApiAiServiceClient(
            http: $this->app->make(
                Factory::class
            ),
            config:
                AiServiceConfig::fromArray(
                    settings: [
                        'base_url' =>
                            'http://127.0.0.1:8001',
                        'token' =>
                            str_repeat('t', 64),
                        'verify_tls' => false,
                        'health_endpoint' =>
                            '/health/ready',
                        'version_endpoint' =>
                            '/version',
                        'analytics_contract_endpoint' =>
                            '/internal/v1/contracts/analytics/validate',
                        'connect_timeout_seconds' => 2,
                        'timeout_seconds' => 5,
                        'retry_attempts' => 2,
                        'retry_delay_milliseconds' => 0,
                        'maximum_request_bytes' =>
                            $maximumRequestBytes,
                        'maximum_response_bytes' =>
                            262144,
                        'user_agent' =>
                            'SmartFactory-DSS/1.0',
                        'log_channel' => 'stack',
                    ],
                    allowInsecureTransport: true
                )
        );
    }

    private function contract(
        string $quantityUnit = 'L'
    ): AnalyticsSnapshotContract {
        $timezone = 'Africa/Casablanca';

        $filter = new AnalyticsFilter(
            startDate:
                CarbonImmutable::parse(
                    '2026-08-01',
                    $timezone
                ),
            endDate:
                CarbonImmutable::parse(
                    '2026-08-02',
                    $timezone
                ),
            timezone: $timezone
        );

        $unit =
            new ProductionKpiUnitSummary(
                quantityUnit: $quantityUnit,
                targetOrderCount: 1,
                recordCount: 1,
                validatedRecordCount: 1,
                provisionalRecordCount: 0,
                targetQuantity: '1000.000',
                actualQuantity: '980.000',
                goodQuantity: '970.000',
                rejectedQuantity: '10.000',
                runtimeMinutes: 420,
                downtimeMinutes: 20,
                achievementPercentage: 98.0,
                rejectionPercentage: 1.02,
                averageProductionRatePerHour:
                    '140.000',
                observedUtilizationPercentage:
                    95.45
            );

        $summary = new ProductionKpiSummary(
            filter: $filter,
            generatedAt:
                CarbonImmutable::parse(
                    '2026-08-02T21:45:00Z'
                ),
            units: [
                $unit,
            ],
            recordCount: 1,
            validatedRecordCount: 1,
            provisionalRecordCount: 0,
            targetOrderCount: 1,
            runtimeMinutes: 420,
            downtimeMinutes: 20
        );

        return new AnalyticsSnapshotContract(
            snapshotId:
                '11111111-1111-4111-8111-111111111111',
            generatedAt:
                CarbonImmutable::parse(
                    '2026-08-02T21:45:00Z'
                ),
            timezone: $timezone,
            productionKpis: $summary
        );
    }
}
