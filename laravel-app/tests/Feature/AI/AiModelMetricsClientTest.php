<?php

namespace Tests\Feature\AI;

use App\Services\AI\Inference\AiModelMetricsClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AiModelMetricsClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai-model-reports.enabled', true);
        config()->set(
            'ai-model-reports.base_url',
            'http://127.0.0.1:8001',
        );
        config()->set(
            'ai-model-reports.token',
            str_repeat('t', 64),
        );
        config()->set('ai-model-reports.verify_tls', false);
        config()->set('ai-model-reports.allow_internal_http', true);
        config()->set(
            'ai-model-reports.connect_timeout_seconds',
            2,
        );
        config()->set('ai-model-reports.timeout_seconds', 5);
        config()->set(
            'ai-model-reports.maximum_response_bytes',
            262144,
        );
        config()->set(
            'ai-model-reports.user_agent',
            'SmartFactory-DSS/1.0',
        );
        config()->set(
            'ai-model-reports.metrics_endpoint',
            '/internal/v1/inference/models/{model_run_id}/metrics/{task}',
        );
    }

    public function test_it_fetches_authenticated_verified_metrics(): void
    {
        $runId = '11111111-1111-4111-8111-111111111111';
        $featureRunId = '22222222-2222-4222-8222-222222222222';
        $requestId = 'laravel-ai-metrics-test-001';

        Http::fake(function (Request $request) use (
            $runId,
            $featureRunId,
            $requestId,
        ) {
            self::assertSame(
                "http://127.0.0.1:8001/internal/v1/inference/models/{$runId}/metrics/production_forecasting",
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
                'status' => 'ok',
                'model_run_id' => $runId,
                'source_feature_run_id' => $featureRunId,
                'task' => 'production_forecasting',
                'selected_model' => 'random_forest_regressor',
                'data_classification' => 'simulated_prototype',
                'metrics' => [
                    'test_metrics' => [
                        'mae' => 10.0,
                        'mse' => 400.0,
                        'rmse' => 20.0,
                        'r2' => 0.8,
                    ],
                ],
                'metric_derivations' => [],
                'limitations' => [
                    'Metrics use simulated-prototype data.',
                ],
                'request_id' => $requestId,
            ]);
        });

        $result = $this->client()->fetch(
            modelRunId: $runId,
            task: 'production_forecasting',
            requestId: $requestId,
        );

        self::assertIsArray($result);
        self::assertEqualsWithDelta(
            400.0,
            (float) $result['metrics']['test_metrics']['mse'],
            0.000001,
        );
        Http::assertSentCount(1);
    }

    public function test_it_rejects_mismatched_or_unsafe_responses(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'ok',
                'model_run_id' =>
                    '11111111-1111-4111-8111-111111111111',
                'source_feature_run_id' =>
                    '22222222-2222-4222-8222-222222222222',
                'task' => 'production_forecasting',
                'selected_model' => 'random_forest_regressor',
                'data_classification' => 'simulated_prototype',
                'metrics' => ['test_metrics' => ['mse' => INF]],
                'metric_derivations' => [],
                'limitations' => ['Prototype only.'],
                'request_id' => 'different-request-id',
            ]),
        ]);

        $result = $this->client()->fetch(
            modelRunId: '11111111-1111-4111-8111-111111111111',
            task: 'production_forecasting',
            requestId: 'laravel-ai-metrics-test-002',
        );

        self::assertNull($result);
    }

    public function test_it_does_not_send_when_metrics_are_disabled(): void
    {
        config()->set('ai-model-reports.enabled', false);
        Http::fake();

        $result = $this->client()->fetch(
            modelRunId: '11111111-1111-4111-8111-111111111111',
            task: 'production_forecasting',
            requestId: 'laravel-ai-metrics-test-003',
        );

        self::assertNull($result);
        Http::assertNothingSent();
    }

    private function client(): AiModelMetricsClient
    {
        return new AiModelMetricsClient(
            $this->app->make(Factory::class),
        );
    }
}
