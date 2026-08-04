<?php

namespace Tests\Unit\AI\Inference;

use App\Services\AI\Inference\InferenceFeatureCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class InferenceFeatureCalculatorTest extends TestCase
{
    public function test_it_builds_the_forecast_contract_from_validated_history(): void
    {
        $rows = [
            $this->productionRow('2026-07-27', 70, 80, 90, 10, 300, 20),
            $this->productionRow('2026-07-29', 80, 90, 100, 10, 320, 15),
            $this->productionRow('2026-07-30', 90, 100, 110, 10, 330, 10),
            $this->productionRow('2026-07-31', 100, 110, 120, 10, 340, 8),
            $this->productionRow('2026-08-01', 110, 120, 130, 10, 350, 7),
            $this->productionRow('2026-08-02', 120, 130, 140, 10, 360, 6),
            $this->productionRow('2026-08-03', 130, 140, 150, 10, 370, 5),
        ];

        $payload = app(InferenceFeatureCalculator::class)->forecast(
            rows: $rows,
            predictionDate: CarbonImmutable::parse('2026-08-04', 'UTC'),
            productionLineCode: 'LINE-01',
            quantityUnit: 'L',
        );

        $this->assertSame('2026-08-04', $payload['prediction_date']);
        $this->assertSame(7, $payload['features']['days_of_history']);
        $this->assertSame(6, $payload['features']['rolling_observation_count_7d']);
        $this->assertSame(130.0, $payload['features']['good_quantity_lag_1d']);
        $this->assertSame(70.0, $payload['features']['good_quantity_lag_7d']);
        $this->assertSame(105.0, $payload['features']['good_quantity_mean_7d']);
        $this->assertSame(80.0, $payload['features']['good_quantity_min_7d']);
        $this->assertSame(130.0, $payload['features']['good_quantity_max_7d']);
        $this->assertEqualsWithDelta(
            10 / 140,
            $payload['features']['rejection_rate_lag_1d'],
            0.000001
        );
        $this->assertEqualsWithDelta(
            140 / 150,
            $payload['features']['achievement_rate_lag_1d'],
            0.000001
        );
    }

    public function test_it_builds_the_anomaly_contract_and_ratios(): void
    {
        $payload = app(InferenceFeatureCalculator::class)->anomaly([
            'started_at_utc' => '2026-08-03T08:00:00Z',
            'production_line_code' => 'LINE-01',
            'product_family_code' => 'VAL-PREM',
            'product_code' => 'ORANGE-1L',
            'shift_code' => 'SHIFT-A',
            'quantity_unit' => 'L',
            'production_order_priority' => 2,
            'target_quantity' => 120,
            'produced_quantity' => 105,
            'good_quantity' => 100,
            'rejected_quantity' => 5,
            'runtime_minutes' => 420,
            'downtime_minutes' => 20,
            'is_validated' => true,
        ]);

        $this->assertTrue($payload['features']['is_validated']);
        $this->assertEqualsWithDelta(
            105 / 120,
            $payload['features']['achievement_ratio'],
            0.000001
        );
        $this->assertEqualsWithDelta(
            5 / 105,
            $payload['features']['rejection_ratio'],
            0.000001
        );
        $this->assertEqualsWithDelta(
            15.0,
            $payload['features']['throughput_per_hour'],
            0.000001
        );
    }

    public function test_it_builds_maintenance_history_without_future_leakage(): void
    {
        $payload = app(InferenceFeatureCalculator::class)->maintenance(
            context: [
                'machine' => [
                    'production_line_code' => 'LINE-01',
                    'machine_code' => 'MIX-01',
                    'machine_type' => 'mixer',
                    'is_critical' => true,
                ],
                'statuses' => [
                    [
                        'occurred_at_utc' => '2026-06-01T08:00:00Z',
                        'status' => 'running',
                        'duration_minutes' => 300,
                    ],
                    [
                        'occurred_at_utc' => '2026-08-01T08:00:00Z',
                        'status' => 'fault',
                        'duration_minutes' => 40,
                    ],
                    [
                        'occurred_at_utc' => '2026-08-02T08:00:00Z',
                        'status' => 'running',
                        'duration_minutes' => 500,
                    ],
                ],
                'downtime' => [
                    [
                        'started_at_utc' => '2026-08-01T09:00:00Z',
                        'severity' => 'critical',
                        'category' => 'unplanned',
                        'downtime_type' => 'breakdown',
                        'duration_minutes' => 60,
                    ],
                ],
                'maintenance' => [
                    [
                        'scheduled_at_utc' => '2026-07-20T08:00:00Z',
                        'maintenance_type' => 'preventive',
                        'downtime_minutes' => 30,
                    ],
                    [
                        'scheduled_at_utc' => '2026-08-02T08:00:00Z',
                        'maintenance_type' => 'corrective',
                        'downtime_minutes' => 45,
                    ],
                ],
            ],
            predictionDate: CarbonImmutable::parse('2026-08-04', 'UTC'),
        );

        $features = $payload['features'];

        $this->assertSame(2, $features['status_event_count_7d']);
        $this->assertSame(1, $features['fault_status_event_count_7d']);
        $this->assertSame(500, $features['running_minutes_7d']);
        $this->assertSame(1, $features['unplanned_downtime_event_count_7d']);
        $this->assertSame(60, $features['unplanned_downtime_minutes_7d']);
        $this->assertSame(2, $features['maintenance_event_count_30d']);
        $this->assertSame(1, $features['preventive_maintenance_count_30d']);
        $this->assertSame(1, $features['corrective_maintenance_count_30d']);
        $this->assertSame(3, $features['days_since_last_failure']);
        $this->assertSame(2, $features['days_since_last_maintenance']);
    }

    /**
     * @return array<string, mixed>
     */
    private function productionRow(
        string $date,
        float $good,
        float $produced,
        float $target,
        float $rejected,
        int $runtime,
        int $downtime,
    ): array {
        return [
            'production_date' => $date,
            'production_line_code' => 'LINE-01',
            'quantity_unit' => 'L',
            'good_quantity' => $good,
            'produced_quantity' => $produced,
            'target_quantity' => $target,
            'rejected_quantity' => $rejected,
            'runtime_minutes' => $runtime,
            'downtime_minutes' => $downtime,
        ];
    }
}
