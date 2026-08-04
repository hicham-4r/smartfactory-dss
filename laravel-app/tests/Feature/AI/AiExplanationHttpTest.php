<?php

namespace Tests\Feature\AI;

use App\Contracts\AI\Explanations\AiExplanationClientInterface;
use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Explanations\AiExplanationResult;
use App\DTOs\AI\Inference\AiInferenceResult;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\AI\Inference\AutomaticInferenceFeatureService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class AiExplanationHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_verified_result_can_generate_role_aware_explanation_without_second_inference(): void
    {
        $this->mockDependencies(
            AiExplanationResult::success(
                requestId: 'laravel-ai-explanation-test',
                data: $this->explanationResponse(
                    'laravel-ai-explanation-test',
                ),
            ),
        );

        $user = $this->userWithRole(RoleName::ProductionManager);

        $inference = $this
            ->actingAs($user)
            ->post(
                route('ai-insights.production.forecast'),
                $this->forecastFormPayload(),
            )
            ->assertOk()
            ->assertSee('927.500')
            ->assertSee('Generate explanation');

        preg_match(
            '/name="snapshot_token"\s+value="([^"]+)"/',
            $inference->getContent(),
            $matches,
        );

        self::assertNotEmpty($matches[1] ?? null);

        $storedSnapshots = session('ai.explanations.snapshots');
        self::assertIsArray($storedSnapshots);
        $encryptedSnapshot = reset($storedSnapshots);
        self::assertIsString($encryptedSnapshot);
        self::assertStringNotContainsString('LINE-01', $encryptedSnapshot);
        self::assertStringNotContainsString('927.5', $encryptedSnapshot);

        $this
            ->actingAs($user)
            ->post(
                route('ai-insights.explanations.generate'),
                [
                    'snapshot_token' => $matches[1],
                    'language' => 'en',
                ],
            )
            ->assertOk()
            ->assertSee('927.500')
            ->assertSee('Guarded AI explanation')
            ->assertSee('The verified forecast remains close to recent history.')
            ->assertSee(
                'PDF, Excel, and CSV exports keep verified facts',
            )
            ->assertSee(
                'separate from the guarded AI narrative.',
            )
            ->assertHeaderContains('Cache-Control', 'no-store');

        $storedReports = session('smartfactory.ai.inference_reports');
        self::assertIsArray($storedReports);
        $storedReport = reset($storedReports);
        self::assertIsArray($storedReport);
        self::assertIsArray($storedReport['explanation'] ?? null);
        self::assertSame(
            'laravel-ai-forecast-test',
            $storedReport['explanation']['inference_request_id'],
        );
        self::assertSame(
            'The verified forecast remains close to recent history.',
            $storedReport['explanation']['narrative']['summary'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'action' => AuditAction::AiExplanationGenerated->value,
        ]);
    }

    public function test_unavailable_ollama_keeps_the_verified_numeric_result_visible(): void
    {
        $this->mockDependencies(
            AiExplanationResult::unavailable(
                requestId: 'laravel-ai-explanation-unavailable',
                message:
                    'The local Ollama explanation dependency is temporarily unavailable.',
                httpStatus: 503,
            ),
        );

        $user = $this->userWithRole(RoleName::ProductionManager);

        $inference = $this
            ->actingAs($user)
            ->post(
                route('ai-insights.production.forecast'),
                $this->forecastFormPayload(),
            )
            ->assertOk();

        preg_match(
            '/name="snapshot_token"\s+value="([^"]+)"/',
            $inference->getContent(),
            $matches,
        );

        $this
            ->actingAs($user)
            ->post(
                route('ai-insights.explanations.generate'),
                [
                    'snapshot_token' => $matches[1],
                    'language' => 'en',
                ],
            )
            ->assertOk()
            ->assertSee('927.500')
            ->assertSee(
                'The local Ollama explanation dependency is temporarily unavailable.',
            )
            ->assertSee(
                'The verified numeric inference result above remains valid and unchanged.',
            );

        $storedReports = session('smartfactory.ai.inference_reports');
        self::assertIsArray($storedReports);
        $storedReport = reset($storedReports);
        self::assertIsArray($storedReport);
        self::assertNull($storedReport['explanation'] ?? null);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'action' => AuditAction::AiExplanationFailed->value,
        ]);
    }

    public function test_snapshot_cannot_be_reused_by_another_user_session(): void
    {
        $this->mockDependencies(
            AiExplanationResult::success(
                requestId: 'laravel-ai-explanation-unused',
                data: $this->explanationResponse(
                    'laravel-ai-explanation-unused',
                ),
            ),
            expectedExplanationCalls: 0,
            expectedModelsCalls: 1,
        );

        $owner = $this->userWithRole(RoleName::ProductionManager);
        $other = $this->userWithRole(RoleName::ProductionManager);

        $inference = $this
            ->actingAs($owner)
            ->post(
                route('ai-insights.production.forecast'),
                $this->forecastFormPayload(),
            )
            ->assertOk();

        preg_match(
            '/name="snapshot_token"\s+value="([^"]+)"/',
            $inference->getContent(),
            $matches,
        );

        $this
            ->actingAs($other)
            ->post(
                route('ai-insights.explanations.generate'),
                [
                    'snapshot_token' => $matches[1],
                    'language' => 'en',
                ],
            )
            ->assertSessionHasErrors('snapshot_token');
    }

    private function mockDependencies(
        AiExplanationResult $explanationResult,
        int $expectedExplanationCalls = 1,
        int $expectedModelsCalls = 2,
    ): void {
        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock) use (
                $expectedModelsCalls,
            ): void {
                $mock->shouldReceive('forecast')
                    ->once()
                    ->andReturn($this->forecastResult());

                $mock->shouldReceive('models')
                    ->times($expectedModelsCalls)
                    ->andReturn($this->registryResult());
            },
        );

        $this->mock(
            AiExplanationClientInterface::class,
            function (MockInterface $mock) use (
                $explanationResult,
                $expectedExplanationCalls,
            ): void {
                $expectation = $mock->shouldReceive('generate')
                    ->times($expectedExplanationCalls);

                if ($expectedExplanationCalls > 0) {
                    $expectation
                        ->withArgs(function (
                            array $payload,
                            string $requestId,
                        ): bool {
                            return $payload['role'] === 'production_manager'
                                && $payload['facts']['result']
                                    ['predicted_good_quantity_next_day']
                                    === 927.5
                                && str_starts_with(
                                    $requestId,
                                    'laravel-ai-explanation-',
                                );
                        })
                        ->andReturn($explanationResult);
                }
            },
        );

        $this->mock(
            AutomaticInferenceFeatureService::class,
            function (MockInterface $mock) use (
                $expectedModelsCalls,
            ): void {
                $mock->shouldReceive('options')
                    ->times($expectedModelsCalls)
                    ->andReturn($this->automaticOptions());
            },
        );
    }

    private function registryResult(): AiInferenceResult
    {
        return AiInferenceResult::success(
            operation: 'models',
            requestId: 'laravel-ai-models-test',
            data: [
                'status' => 'ready',
                'model_run_id' => '11111111-1111-4111-8111-111111111111',
                'source_feature_run_id' =>
                    '22222222-2222-4222-8222-222222222222',
                'tasks' => [
                    'production_forecasting',
                    'production_anomaly',
                    'maintenance_risk',
                ],
                'data_classification' => 'simulated_prototype',
                'request_id' => 'laravel-ai-models-test',
            ],
        );
    }

    private function forecastResult(): AiInferenceResult
    {
        return AiInferenceResult::success(
            operation: 'production_forecast',
            requestId: 'laravel-ai-forecast-test',
            data: [
                'status' => 'ok',
                'predicted_good_quantity_next_day' => 927.5,
                'prediction_date' => '2026-08-05',
                'metadata' => [
                    'model_run_id' =>
                        '11111111-1111-4111-8111-111111111111',
                    'source_feature_run_id' =>
                        '22222222-2222-4222-8222-222222222222',
                    'model_name' => 'random_forest_regressor',
                    'data_classification' => 'simulated_prototype',
                    'limitations' => [
                        'Simulated-prototype data only.',
                        'The forecast is not an industrial production commitment.',
                    ],
                ],
                'request_id' => 'laravel-ai-forecast-test',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function explanationResponse(string $requestId): array
    {
        return [
            'status' => 'generated',
            'contract_name' => 'smartfactory.llm.explanation',
            'contract_version' => 'v1',
            'explanation_id' =>
                '33333333-3333-4333-8333-333333333333',
            'explanation_type' => 'production_forecast',
            'role' => 'production_manager',
            'language' => 'en',
            'data_classification' => 'simulated_prototype',
            'narrative' => [
                'summary' =>
                    'The verified forecast remains close to recent history.',
                'observations' => [
                    'The verified forecast is 927.5 L.',
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

    /**
     * @return array<string, mixed>
     */
    private function forecastFormPayload(): array
    {
        return [
            'prediction_date' => '2026-08-05',
            'model_run_id' => '',
            'features' => [
                'production_line_code' => 'LINE-01',
                'quantity_unit' => 'L',
                'days_of_history' => '30',
                'rolling_observation_count_7d' => '7',
                'day_of_week' => '1',
                'month' => '8',
                'good_quantity_lag_1d' => '900',
                'good_quantity_lag_7d' => '880',
                'good_quantity_mean_7d' => '890',
                'good_quantity_min_7d' => '850',
                'good_quantity_max_7d' => '930',
                'produced_quantity_lag_1d' => '920',
                'target_quantity_lag_1d' => '950',
                'runtime_minutes_lag_1d' => '420',
                'downtime_minutes_lag_1d' => '20',
                'rejection_rate_lag_1d' => '0.02',
                'achievement_rate_lag_1d' => '0.968',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function automaticOptions(): array
    {
        return [
            'production_lines' => [],
            'quantity_units' => [],
            'production_records' => [],
            'machines' => [],
            'default_forecast_date' => '2026-08-05',
            'default_maintenance_date' => '2026-08-05',
        ];
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        $user->assignRole($role->value);

        return $user;
    }
}
