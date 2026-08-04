<?php

namespace Tests\Feature\AI;

use App\DTOs\AI\Inference\AiInferenceConfig;
use App\Enums\AI\AiInferenceStatus;
use App\Services\AI\Inference\FastApiAiInferenceClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FastApiAiInferenceClientTest extends TestCase
{
    public function test_models_request_uses_internal_authentication_and_maps_registry(): void
    {
        $requestId = 'laravel-ai-models-001';

        Http::fake(function (Request $request) use ($requestId) {
            self::assertSame(
                'http://127.0.0.1:8001/internal/v1/inference/models',
                $request->url(),
            );
            self::assertTrue($request->hasHeader(
                'Authorization',
                'Bearer '.str_repeat('t', 64),
            ));
            self::assertTrue($request->hasHeader(
                'X-Request-ID',
                $requestId,
            ));

            return Http::response([
                'status' => 'ready',
                'model_run_id' => '11111111-1111-4111-8111-111111111111',
                'source_feature_run_id' => '22222222-2222-4222-8222-222222222222',
                'tasks' => [
                    'production_forecasting',
                    'production_anomaly',
                    'maintenance_risk',
                ],
                'data_classification' => 'simulated_prototype',
                'request_id' => $requestId,
            ]);
        });

        $result = $this->client()->models($requestId);

        self::assertTrue($result->succeeded());
        self::assertSame(
            '11111111-1111-4111-8111-111111111111',
            $result->data['model_run_id'],
        );
        Http::assertSentCount(1);
    }

    public function test_forecast_sends_only_structured_payload_and_maps_metadata(): void
    {
        $requestId = 'laravel-ai-forecast-001';
        $payload = $this->forecastPayload();

        Http::fake(function (Request $request) use ($requestId, $payload) {
            self::assertSame('POST', $request->method());
            self::assertSame($payload, $request->data());
            self::assertArrayNotHasKey('token', $request->data());

            return Http::response([
                'status' => 'ok',
                'predicted_good_quantity_next_day' => 927.5,
                'prediction_date' => '2026-08-04',
                'metadata' => $this->metadata(),
                'request_id' => $requestId,
            ]);
        });

        $result = $this->client()->forecast($payload, $requestId);

        self::assertTrue($result->succeeded());
        self::assertSame(
            927.5,
            $result->data['predicted_good_quantity_next_day'],
        );
    }

    public function test_mismatched_response_request_id_is_rejected_safely(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'predicted_good_quantity_next_day' => 900,
                'prediction_date' => '2026-08-04',
                'metadata' => $this->metadata(),
                'request_id' => 'different-request',
            ]),
        ]);

        $result = $this->client()->forecast(
            $this->forecastPayload(),
            'laravel-ai-forecast-002',
        );

        self::assertSame(
            AiInferenceStatus::InvalidResponse,
            $result->status,
        );
    }

    public function test_validation_failure_is_returned_without_exposing_fastapi_details(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 'validation_error',
                    'details' => [
                        ['field' => 'secret_internal_field'],
                    ],
                ],
            ], 422),
        ]);

        $result = $this->client()->maintenanceRisk(
            ['prediction_date' => '2026-08-04', 'features' => []],
            'laravel-ai-maintenance-001',
        );

        self::assertSame(AiInferenceStatus::Rejected, $result->status);
        self::assertSame(
            'The inference feature values did not satisfy the model contract.',
            $result->message,
        );
        self::assertStringNotContainsString(
            'secret_internal_field',
            (string) $result->message,
        );
    }

    public function test_oversized_request_is_rejected_before_http_transport(): void
    {
        Http::fake();

        $result = $this
            ->client(maximumRequestBytes: 1024)
            ->forecast([
                'prediction_date' => '2026-08-04',
                'features' => [
                    'production_line_code' => str_repeat('L', 5000),
                ],
            ], 'laravel-ai-forecast-003');

        self::assertSame(AiInferenceStatus::Rejected, $result->status);
        Http::assertNothingSent();
    }

    private function client(
        int $maximumRequestBytes = 262144,
    ): FastApiAiInferenceClient {
        return new FastApiAiInferenceClient(
            http: $this->app->make(Factory::class),
            config: AiInferenceConfig::fromArray(
                settings: [
                    'base_url' => 'http://127.0.0.1:8001',
                    'token' => str_repeat('t', 64),
                    'verify_tls' => false,
                    'models_endpoint' => '/internal/v1/inference/models',
                    'forecast_endpoint' => '/internal/v1/inference/production/forecast',
                    'anomaly_endpoint' => '/internal/v1/inference/production/anomaly',
                    'maintenance_risk_endpoint' => '/internal/v1/inference/maintenance/risk',
                    'connect_timeout_seconds' => 2,
                    'timeout_seconds' => 5,
                    'retry_attempts' => 2,
                    'retry_delay_milliseconds' => 0,
                    'maximum_request_bytes' => $maximumRequestBytes,
                    'maximum_response_bytes' => 262144,
                    'user_agent' => 'SmartFactory-DSS/1.0',
                    'log_channel' => 'stack',
                ],
                allowInsecureTransport: true,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function forecastPayload(): array
    {
        return [
            'prediction_date' => '2026-08-04',
            'model_run_id' => null,
            'features' => [
                'production_line_code' => 'LINE-01',
                'quantity_unit' => 'L',
                'days_of_history' => 30,
                'rolling_observation_count_7d' => 7,
                'day_of_week' => 0,
                'month' => 8,
                'good_quantity_lag_1d' => 900.0,
                'good_quantity_lag_7d' => 880.0,
                'good_quantity_mean_7d' => 890.0,
                'good_quantity_min_7d' => 850.0,
                'good_quantity_max_7d' => 930.0,
                'produced_quantity_lag_1d' => 920.0,
                'target_quantity_lag_1d' => 950.0,
                'runtime_minutes_lag_1d' => 420,
                'downtime_minutes_lag_1d' => 20,
                'rejection_rate_lag_1d' => 0.02,
                'achievement_rate_lag_1d' => 0.968,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        return [
            'model_run_id' => '11111111-1111-4111-8111-111111111111',
            'source_feature_run_id' => '22222222-2222-4222-8222-222222222222',
            'model_name' => 'linear_regression',
            'data_classification' => 'simulated_prototype',
            'limitations' => [
                'Metrics are based only on simulated-prototype data.',
            ],
        ];
    }
}
