<?php

namespace Tests\Feature\AI;

use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Inference\AiInferenceResult;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class AiInsightsHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProductionWorkflowPermissionsSeeder::class);
    }

    public function test_production_manager_can_open_ai_insights_dashboard(): void
    {
        $this->fakeRegistry();

        $this
            ->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->get(route('ai-insights.index'))
            ->assertOk()
            ->assertSee('AI-assisted operational insights')
            ->assertSee('Verified model registry')
            ->assertSee('production_forecasting')
            ->assertSee('simulated_prototype')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'private');
    }

    public function test_operator_cannot_open_ai_insights_dashboard(): void
    {
        $this->fakeRegistry();

        $this
            ->actingAs($this->userWithRole(RoleName::Operator))
            ->get(route('ai-insights.index'))
            ->assertForbidden();
    }

    public function test_production_manager_can_submit_typed_forecast_features(): void
    {
        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('forecast')
                    ->once()
                    ->withArgs(function (
                        array $payload,
                        string $requestId,
                    ): bool {
                        return is_float(
                            $payload['features']['good_quantity_lag_1d'],
                        )
                            && is_int(
                                $payload['features']['runtime_minutes_lag_1d'],
                            )
                            && str_starts_with(
                                $requestId,
                                'laravel-ai-forecast-',
                            );
                    })
                    ->andReturn($this->forecastResult());

                $mock->shouldReceive('models')
                    ->once()
                    ->andReturn($this->registryResult());
            },
        );

        $this
            ->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->post(
                route('ai-insights.production.forecast'),
                $this->forecastFormPayload(),
            )
            ->assertOk()
            ->assertSee('927.500')
            ->assertSee('linear_regression')
            ->assertSee('simulated-prototype data');
    }

    public function test_maintenance_manager_can_submit_maintenance_risk(): void
    {
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
            ->actingAs($this->userWithRole(RoleName::MaintenanceManager))
            ->post(
                route('ai-insights.maintenance.risk'),
                $this->maintenanceFormPayload(),
            )
            ->assertOk()
            ->assertSee('72.0%')
            ->assertSee('high')
            ->assertSee('95.0');
    }

    public function test_production_manager_cannot_submit_maintenance_risk(): void
    {
        $this->fakeRegistry();

        $this
            ->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->post(
                route('ai-insights.maintenance.risk'),
                $this->maintenanceFormPayload(),
            )
            ->assertForbidden();
    }

    private function fakeRegistry(): void
    {
        $this->mock(
            AiInferenceClientInterface::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('models')
                    ->zeroOrMoreTimes()
                    ->andReturn($this->registryResult());
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
            requestId: 'laravel-ai-forecast-test',
            data: [
                'status' => 'ok',
                'predicted_good_quantity_next_day' => 927.5,
                'prediction_date' => '2026-08-04',
                'metadata' => $this->metadata('linear_regression'),
                'request_id' => 'laravel-ai-forecast-test',
            ],
        );
    }

    private function maintenanceResult(): AiInferenceResult
    {
        return AiInferenceResult::success(
            operation: 'maintenance_risk',
            requestId: 'laravel-ai-maintenance-test',
            data: [
                'status' => 'ok',
                'failure_probability_next_7d' => 0.72,
                'predicted_unplanned_downtime_minutes_next_7d' => 95.0,
                'priority' => 'high',
                'prediction_date' => '2026-08-04',
                'metadata' => $this->metadata(
                    'random_forest_classifier+gradient_boosting_regressor',
                ),
                'request_id' => 'laravel-ai-maintenance-test',
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

    /**
     * @return array<string, mixed>
     */
    private function forecastFormPayload(): array
    {
        return [
            'prediction_date' => '2026-08-04',
            'model_run_id' => '',
            'features' => [
                'production_line_code' => 'LINE-01',
                'quantity_unit' => 'L',
                'days_of_history' => '30',
                'rolling_observation_count_7d' => '7',
                'day_of_week' => '0',
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
    private function maintenanceFormPayload(): array
    {
        return [
            'prediction_date' => '2026-08-04',
            'model_run_id' => '',
            'features' => [
                'production_line_code' => 'LINE-01',
                'machine_code' => 'MIX-01',
                'machine_type' => 'mixer',
                'is_critical' => '1',
                'days_observed' => '60',
                'status_event_count_7d' => '12',
                'fault_status_event_count_7d' => '2',
                'running_minutes_7d' => '5100',
                'fault_minutes_7d' => '80',
                'downtime_event_count_7d' => '3',
                'unplanned_downtime_event_count_7d' => '2',
                'total_downtime_minutes_7d' => '130',
                'unplanned_downtime_minutes_7d' => '95',
                'maintenance_event_count_30d' => '4',
                'preventive_maintenance_count_30d' => '2',
                'corrective_maintenance_count_30d' => '2',
                'maintenance_downtime_minutes_30d' => '160',
                'days_since_last_failure' => '3',
                'days_since_last_maintenance' => '8',
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
