<?php

namespace Tests\Feature\AI;

use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Inference\AiInferenceResult;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\AI\Inference\AutomaticInferenceFeatureService;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class AutomaticAiInsightsHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProductionWorkflowPermissionsSeeder::class);
    }

    public function test_production_manager_can_run_automatic_forecast(): void
    {
        $preparedPayload = [
            'prediction_date' => '2026-08-04',
            'model_run_id' => null,
            'features' => [
                'production_line_code' => 'LINE-01',
                'quantity_unit' => 'L',
                'days_of_history' => 30,
                'rolling_observation_count_7d' => 7,
                'day_of_week' => 0,
                'month' => 8,
                'good_quantity_lag_1d' => 100.0,
                'good_quantity_lag_7d' => 90.0,
                'good_quantity_mean_7d' => 95.0,
                'good_quantity_min_7d' => 80.0,
                'good_quantity_max_7d' => 110.0,
                'produced_quantity_lag_1d' => 105.0,
                'target_quantity_lag_1d' => 120.0,
                'runtime_minutes_lag_1d' => 420,
                'downtime_minutes_lag_1d' => 20,
                'rejection_rate_lag_1d' => 0.01,
                'achievement_rate_lag_1d' => 0.875,
            ],
        ];

        $this->mock(
            AutomaticInferenceFeatureService::class,
            function (MockInterface $mock) use ($preparedPayload): void {
                $mock->shouldReceive('forecastPayload')
                    ->once()
                    ->with(
                        'LINE-01',
                        'L',
                        '2026-08-04',
                        null
                    )
                    ->andReturn($preparedPayload);

                $mock->shouldReceive('options')
                    ->once()
                    ->andReturn($this->options());
            },
        );

        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock) use ($preparedPayload): void {
                $mock->shouldReceive('forecast')
                    ->once()
                    ->withArgs(
                        static function (
                            array $payload,
                            string $requestId,
                        ) use ($preparedPayload): bool {
                            return $payload === $preparedPayload
                                && str_starts_with(
                                    $requestId,
                                    'laravel-ai-automatic-forecast-'
                                );
                        }
                    )
                    ->andReturn($this->forecastResult());

                $mock->shouldReceive('models')
                    ->once()
                    ->andReturn($this->registryResult());
            },
        );

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->post(
                route(
                    'ai-insights.automatic.production.forecast'
                ),
                [
                    'production_line_code' => 'LINE-01',
                    'quantity_unit' => 'L',
                    'prediction_date' => '2026-08-04',
                ]
            )
            ->assertOk()
            ->assertSee('108,493.696')
            ->assertSee('random_forest_regressor');
    }

    public function test_production_manager_can_run_automatic_anomaly(): void
    {
        $preparedPayload = [
            'event_time_utc' => '2026-08-03T08:00:00+00:00',
            'model_run_id' => null,
            'features' => [
                'production_line_code' => 'LINE-01',
                'product_family_code' => 'VAL-PREM',
                'product_code' => 'ORANGE-1L',
                'shift_code' => 'SHIFT-A',
                'quantity_unit' => 'L',
                'production_order_priority' => 2,
                'target_quantity' => 120.0,
                'produced_quantity' => 105.0,
                'good_quantity' => 100.0,
                'rejected_quantity' => 5.0,
                'runtime_minutes' => 420,
                'downtime_minutes' => 20,
                'achievement_ratio' => 0.875,
                'rejection_ratio' => 0.047619,
                'good_yield_ratio' => 0.952381,
                'throughput_per_hour' => 15.0,
                'downtime_ratio' => 0.045455,
                'is_validated' => true,
            ],
        ];

        $this->mock(
            AutomaticInferenceFeatureService::class,
            function (MockInterface $mock) use ($preparedPayload): void {
                $mock->shouldReceive('anomalyPayload')
                    ->once()
                    ->with(19, null)
                    ->andReturn($preparedPayload);

                $mock->shouldReceive('options')
                    ->once()
                    ->andReturn($this->options());
            },
        );

        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('anomaly')
                    ->once()
                    ->andReturn($this->anomalyResult());

                $mock->shouldReceive('models')
                    ->once()
                    ->andReturn($this->registryResult());
            },
        );

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->post(
                route(
                    'ai-insights.automatic.production.anomaly'
                ),
                [
                    'production_record_id' => 19,
                ]
            )
            ->assertOk()
            ->assertSee('Potential anomaly')
            ->assertSee('isolation_forest');
    }

    public function test_maintenance_manager_can_run_automatic_risk_analysis(): void
    {
        $preparedPayload = [
            'prediction_date' => '2026-08-04',
            'model_run_id' => null,
            'features' => [
                'production_line_code' => 'LINE-01',
                'machine_code' => 'MIX-01',
                'machine_type' => 'mixer',
                'is_critical' => true,
                'days_observed' => 64,
                'status_event_count_7d' => 10,
                'fault_status_event_count_7d' => 1,
                'running_minutes_7d' => 5000,
                'fault_minutes_7d' => 40,
                'downtime_event_count_7d' => 2,
                'unplanned_downtime_event_count_7d' => 1,
                'total_downtime_minutes_7d' => 80,
                'unplanned_downtime_minutes_7d' => 60,
                'maintenance_event_count_30d' => 2,
                'preventive_maintenance_count_30d' => 1,
                'corrective_maintenance_count_30d' => 1,
                'maintenance_downtime_minutes_30d' => 75,
                'days_since_last_failure' => 3,
                'days_since_last_maintenance' => 2,
            ],
        ];

        $this->mock(
            AutomaticInferenceFeatureService::class,
            function (MockInterface $mock) use ($preparedPayload): void {
                $mock->shouldReceive('maintenancePayload')
                    ->once()
                    ->with(7, '2026-08-04', null)
                    ->andReturn($preparedPayload);

                $mock->shouldReceive('options')
                    ->once()
                    ->andReturn($this->options());
            },
        );

        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('maintenanceRisk')
                    ->once()
                    ->andReturn($this->maintenanceResult());

                $mock->shouldReceive('models')
                    ->once()
                    ->andReturn($this->registryResult());
            },
        );

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->post(
                route(
                    'ai-insights.automatic.maintenance.risk'
                ),
                [
                    'machine_id' => 7,
                    'prediction_date' => '2026-08-04',
                ]
            )
            ->assertOk()
            ->assertSee('72.0%')
            ->assertSee('high');
    }

    public function test_production_manager_cannot_run_automatic_maintenance(): void
    {
        $this->mock(
            AutomaticInferenceFeatureService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('maintenancePayload');
            },
        );

        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('maintenanceRisk');
            },
        );

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->post(
                route(
                    'ai-insights.automatic.maintenance.risk'
                ),
                [
                    'machine_id' => 7,
                    'prediction_date' => '2026-08-04',
                ]
            )
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'production_lines' => [
                [
                    'code' => 'LINE-01',
                    'label' => 'LINE-01 — Line 1',
                ],
            ],
            'quantity_units' => ['L'],
            'production_records' => [
                [
                    'id' => 19,
                    'label' => '2026-08-03 — LINE-01 — ORANGE-1L — PR-19',
                ],
            ],
            'machines' => [
                [
                    'id' => 7,
                    'label' => 'LINE-01 — MIX-01 — Mixer (mixer)',
                ],
            ],
            'default_forecast_date' => '2026-08-04',
            'default_maintenance_date' => '2026-08-04',
        ];
    }

    private function registryResult(): AiInferenceResult
    {
        return AiInferenceResult::success(
            operation: 'models',
            requestId: 'laravel-ai-models-test',
            data: [
                'status' => 'ready',
                'model_run_id' => '11111111-1111-4111-8111-111111111111',
                'source_feature_run_id' => '22222222-2222-4222-8222-222222222222',
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
            requestId: 'laravel-ai-automatic-forecast-test',
            data: [
                'status' => 'ok',
                'predicted_good_quantity_next_day' => 108493.696,
                'prediction_date' => '2026-08-04',
                'metadata' => $this->metadata(
                    'random_forest_regressor'
                ),
                'request_id' => 'laravel-ai-automatic-forecast-test',
            ],
        );
    }

    private function anomalyResult(): AiInferenceResult
    {
        return AiInferenceResult::success(
            operation: 'production_anomaly',
            requestId: 'laravel-ai-automatic-anomaly-test',
            data: [
                'status' => 'ok',
                'anomaly_score' => 0.08,
                'threshold' => 0.04,
                'is_anomaly' => true,
                'event_time_utc' => '2026-08-03T08:00:00+00:00',
                'metadata' => $this->metadata('isolation_forest'),
                'request_id' => 'laravel-ai-automatic-anomaly-test',
            ],
        );
    }

    private function maintenanceResult(): AiInferenceResult
    {
        return AiInferenceResult::success(
            operation: 'maintenance_risk',
            requestId: 'laravel-ai-automatic-maintenance-test',
            data: [
                'status' => 'ok',
                'failure_probability_next_7d' => 0.72,
                'predicted_unplanned_downtime_minutes_next_7d' => 95.0,
                'priority' => 'high',
                'prediction_date' => '2026-08-04',
                'metadata' => $this->metadata(
                    'random_forest_classifier'
                    .'+gradient_boosting_regressor'
                ),
                'request_id' => 'laravel-ai-automatic-maintenance-test',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(string $modelName): array
    {
        return [
            'model_run_id' => '11111111-1111-4111-8111-111111111111',
            'source_feature_run_id' => '22222222-2222-4222-8222-222222222222',
            'model_name' => $modelName,
            'data_classification' => 'simulated_prototype',
            'limitations' => [
                'Metrics are based only on simulated-prototype data.',
            ],
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
