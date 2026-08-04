<?php

namespace Tests\Feature\AI;

use App\DTOs\AI\Explanations\AiExplanationSnapshot;
use App\Enums\RoleName;
use App\Exceptions\AI\AiExplanationPreparationException;
use App\Models\User;
use App\Services\AI\Explanations\AiExplanationPayloadFactory;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AiExplanationPayloadFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_production_manager_receives_strict_forecast_payload(): void
    {
        $payload = app(AiExplanationPayloadFactory::class)->make(
            user: $this->userWithRole(RoleName::ProductionManager),
            snapshot: $this->forecastSnapshot(),
            language: 'en',
        );

        self::assertSame('production_manager', $payload['role']);
        self::assertSame(
            'production_forecast',
            $payload['facts']['explanation_type'],
        );
        self::assertSame(
            995.0,
            $payload['facts']['result']['predicted_good_quantity_next_day'],
        );
        self::assertArrayNotHasKey(
            'good_quantity_min_7d',
            $payload['facts']['history'],
        );
        self::assertArrayNotHasKey(
            'authorization',
            $payload,
        );
    }

    public function test_operator_cannot_prepare_a_production_explanation(): void
    {
        $this->expectException(
            AiExplanationPreparationException::class,
        );

        app(AiExplanationPayloadFactory::class)->make(
            user: $this->userWithRole(RoleName::Operator),
            snapshot: $this->forecastSnapshot(),
            language: 'en',
        );
    }

    private function forecastSnapshot(): AiExplanationSnapshot
    {
        return new AiExplanationSnapshot(
            token: '33333333-3333-4333-8333-333333333333',
            userId: 1,
            sessionFingerprint: str_repeat('a', 64),
            operation: 'production_forecast',
            inferenceRequestId: 'laravel-ai-forecast-test',
            inferencePayload: [
                'prediction_date' => '2026-08-05',
                'features' => [
                    'production_line_code' => 'LINE-01',
                    'quantity_unit' => 'L',
                    'days_of_history' => 30,
                    'rolling_observation_count_7d' => 7,
                    'good_quantity_lag_1d' => 1000.0,
                    'good_quantity_mean_7d' => 980.0,
                    'target_quantity_lag_1d' => 1050.0,
                    'runtime_minutes_lag_1d' => 420,
                    'downtime_minutes_lag_1d' => 20,
                    'rejection_rate_lag_1d' => 0.01,
                    'achievement_rate_lag_1d' => 0.95,
                    'ignored_secret' => 'must-not-be-forwarded',
                ],
            ],
            inferenceData: [
                'status' => 'ok',
                'predicted_good_quantity_next_day' => 995.0,
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
                        'The forecast is not an industrial commitment.',
                    ],
                ],
                'request_id' => 'laravel-ai-forecast-test',
            ],
            reportToken: null,
            expiresAt: CarbonImmutable::now()->addMinutes(15),
        );
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
