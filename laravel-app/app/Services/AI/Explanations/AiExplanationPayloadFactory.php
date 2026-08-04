<?php

namespace App\Services\AI\Explanations;

use App\DTOs\AI\Explanations\AiExplanationSnapshot;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Exceptions\AI\AiExplanationPreparationException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class AiExplanationPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(
        User $user,
        AiExplanationSnapshot $snapshot,
        string $language,
    ): array {
        if (! in_array($language, ['en', 'fr'], true)) {
            throw new AiExplanationPreparationException(
                'The explanation language is unsupported.',
            );
        }

        $role = $this->roleFor(
            user: $user,
            operation: $snapshot->operation,
        );
        $metadata = $this->modelFacts(
            $snapshot->inferenceData['metadata'] ?? null,
        );

        $facts = match ($snapshot->operation) {
            'production_forecast' => $this->forecastFacts(
                $snapshot,
                $metadata,
            ),
            'production_anomaly' => $this->anomalyFacts(
                $snapshot,
                $metadata,
            ),
            'maintenance_risk' => $this->maintenanceFacts(
                $snapshot,
                $metadata,
            ),
            default => throw new AiExplanationPreparationException(
                'The inference operation cannot be explained.',
            ),
        };

        return [
            'contract_name' => 'smartfactory.llm.explanation',
            'contract_version' => 'v1',
            'explanation_id' => (string) Str::uuid(),
            'requested_at' => CarbonImmutable::now()
                ->utc()
                ->toIso8601String(),
            'role' => $role,
            'language' => $language,
            'facts' => $facts,
        ];
    }

    private function roleFor(
        User $user,
        string $operation,
    ): string {
        if ($user->hasRole(RoleName::Administrator->value)) {
            return 'administrator';
        }

        if (
            in_array(
                $operation,
                ['production_forecast', 'production_anomaly'],
                true,
            )
        ) {
            if (
                ! $user->can(
                    PermissionName::ViewProductionAiExplanations->value,
                )
            ) {
                throw new AiExplanationPreparationException(
                    'The account cannot request production explanations.',
                );
            }

            if ($user->hasRole(RoleName::ProductionManager->value)) {
                return 'production_manager';
            }

            if ($user->hasRole(RoleName::ProductionSupervisor->value)) {
                return 'production_supervisor';
            }
        }

        if ($operation === 'maintenance_risk') {
            if (
                ! $user->can(
                    PermissionName::ViewMaintenanceAiRecommendations->value,
                )
            ) {
                throw new AiExplanationPreparationException(
                    'The account cannot request maintenance explanations.',
                );
            }

            if ($user->hasRole(RoleName::MaintenanceManager->value)) {
                return 'maintenance_manager';
            }
        }

        throw new AiExplanationPreparationException(
            'The account role is not authorized for this explanation.',
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function forecastFacts(
        AiExplanationSnapshot $snapshot,
        array $metadata,
    ): array {
        $payload = $snapshot->inferencePayload;
        $data = $snapshot->inferenceData;
        $features = $this->arrayValue($payload, 'features');

        return [
            'explanation_type' => 'production_forecast',
            'prediction_date' => $this->stringValue(
                $data,
                'prediction_date',
                40,
            ),
            'production_line_code' => $this->safeCode(
                $features,
                'production_line_code',
            ),
            'quantity_unit' => $this->stringValue(
                $features,
                'quantity_unit',
                30,
            ),
            'history' => [
                'days_of_history' => $this->integerValue(
                    $features,
                    'days_of_history',
                ),
                'rolling_observation_count_7d' => $this->integerValue(
                    $features,
                    'rolling_observation_count_7d',
                ),
                'good_quantity_lag_1d' => $this->numberValue(
                    $features,
                    'good_quantity_lag_1d',
                ),
                'good_quantity_mean_7d' => $this->numberValue(
                    $features,
                    'good_quantity_mean_7d',
                ),
                'target_quantity_lag_1d' => $this->numberValue(
                    $features,
                    'target_quantity_lag_1d',
                ),
                'runtime_minutes_lag_1d' => $this->integerValue(
                    $features,
                    'runtime_minutes_lag_1d',
                ),
                'downtime_minutes_lag_1d' => $this->integerValue(
                    $features,
                    'downtime_minutes_lag_1d',
                ),
                'rejection_rate_lag_1d' => $this->nullableNumberValue(
                    $features,
                    'rejection_rate_lag_1d',
                ),
                'achievement_rate_lag_1d' => $this->nullableNumberValue(
                    $features,
                    'achievement_rate_lag_1d',
                ),
            ],
            'result' => [
                'predicted_good_quantity_next_day' => $this->numberValue(
                    $data,
                    'predicted_good_quantity_next_day',
                ),
            ],
            'model' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function anomalyFacts(
        AiExplanationSnapshot $snapshot,
        array $metadata,
    ): array {
        $payload = $snapshot->inferencePayload;
        $data = $snapshot->inferenceData;
        $features = $this->arrayValue($payload, 'features');

        return [
            'explanation_type' => 'production_anomaly',
            'context' => [
                'event_time_utc' => $this->stringValue(
                    $data,
                    'event_time_utc',
                    80,
                ),
                'production_line_code' => $this->safeCode(
                    $features,
                    'production_line_code',
                ),
                'product_family_code' => $this->safeCode(
                    $features,
                    'product_family_code',
                ),
                'product_code' => $this->safeCode(
                    $features,
                    'product_code',
                ),
                'shift_code' => $this->safeCode(
                    $features,
                    'shift_code',
                ),
                'quantity_unit' => $this->stringValue(
                    $features,
                    'quantity_unit',
                    30,
                ),
                'target_quantity' => $this->numberValue(
                    $features,
                    'target_quantity',
                ),
                'produced_quantity' => $this->numberValue(
                    $features,
                    'produced_quantity',
                ),
                'good_quantity' => $this->numberValue(
                    $features,
                    'good_quantity',
                ),
                'rejected_quantity' => $this->numberValue(
                    $features,
                    'rejected_quantity',
                ),
                'runtime_minutes' => $this->integerValue(
                    $features,
                    'runtime_minutes',
                ),
                'downtime_minutes' => $this->integerValue(
                    $features,
                    'downtime_minutes',
                ),
                'achievement_ratio' => $this->nullableNumberValue(
                    $features,
                    'achievement_ratio',
                ),
                'rejection_ratio' => $this->nullableNumberValue(
                    $features,
                    'rejection_ratio',
                ),
                'good_yield_ratio' => $this->nullableNumberValue(
                    $features,
                    'good_yield_ratio',
                ),
                'throughput_per_hour' => $this->nullableNumberValue(
                    $features,
                    'throughput_per_hour',
                ),
                'downtime_ratio' => $this->nullableNumberValue(
                    $features,
                    'downtime_ratio',
                ),
                'is_validated' => $this->booleanValue(
                    $features,
                    'is_validated',
                ),
            ],
            'result' => [
                'anomaly_score' => $this->numberValue(
                    $data,
                    'anomaly_score',
                    allowNegative: true,
                ),
                'threshold' => $this->numberValue(
                    $data,
                    'threshold',
                    allowNegative: true,
                ),
                'is_anomaly' => $this->booleanValue(
                    $data,
                    'is_anomaly',
                ),
            ],
            'model' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function maintenanceFacts(
        AiExplanationSnapshot $snapshot,
        array $metadata,
    ): array {
        $payload = $snapshot->inferencePayload;
        $data = $snapshot->inferenceData;
        $features = $this->arrayValue($payload, 'features');

        return [
            'explanation_type' => 'maintenance_risk',
            'context' => [
                'prediction_date' => $this->stringValue(
                    $data,
                    'prediction_date',
                    40,
                ),
                'production_line_code' => $this->safeCode(
                    $features,
                    'production_line_code',
                ),
                'machine_code' => $this->safeCode(
                    $features,
                    'machine_code',
                ),
                'machine_type' => $this->safeCode(
                    $features,
                    'machine_type',
                ),
                'is_critical' => $this->booleanValue(
                    $features,
                    'is_critical',
                ),
                'days_observed' => $this->integerValue(
                    $features,
                    'days_observed',
                ),
                'fault_status_event_count_7d' => $this->integerValue(
                    $features,
                    'fault_status_event_count_7d',
                ),
                'fault_minutes_7d' => $this->integerValue(
                    $features,
                    'fault_minutes_7d',
                ),
                'unplanned_downtime_event_count_7d' => $this->integerValue(
                    $features,
                    'unplanned_downtime_event_count_7d',
                ),
                'unplanned_downtime_minutes_7d' => $this->integerValue(
                    $features,
                    'unplanned_downtime_minutes_7d',
                ),
                'maintenance_event_count_30d' => $this->integerValue(
                    $features,
                    'maintenance_event_count_30d',
                ),
                'preventive_maintenance_count_30d' => $this->integerValue(
                    $features,
                    'preventive_maintenance_count_30d',
                ),
                'corrective_maintenance_count_30d' => $this->integerValue(
                    $features,
                    'corrective_maintenance_count_30d',
                ),
                'maintenance_downtime_minutes_30d' => $this->integerValue(
                    $features,
                    'maintenance_downtime_minutes_30d',
                ),
                'days_since_last_failure' => $this->nullableIntegerValue(
                    $features,
                    'days_since_last_failure',
                ),
                'days_since_last_maintenance' => $this->nullableIntegerValue(
                    $features,
                    'days_since_last_maintenance',
                ),
            ],
            'result' => [
                'failure_probability_next_7d' => $this->probabilityValue(
                    $data,
                    'failure_probability_next_7d',
                ),
                'predicted_unplanned_downtime_minutes_next_7d' =>
                    $this->numberValue(
                        $data,
                        'predicted_unplanned_downtime_minutes_next_7d',
                    ),
                'priority' => $this->priorityValue(
                    $data,
                    'priority',
                ),
            ],
            'model' => $metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelFacts(mixed $value): array
    {
        if (! is_array($value)) {
            throw new AiExplanationPreparationException(
                'Verified model metadata is missing.',
            );
        }

        $limitations = $value['limitations'] ?? null;

        if (
            ! is_array($limitations)
            || $limitations === []
            || count($limitations) > 10
        ) {
            throw new AiExplanationPreparationException(
                'Verified model limitations are invalid.',
            );
        }

        $normalizedLimitations = [];

        foreach ($limitations as $limitation) {
            if (
                ! is_string($limitation)
                || trim($limitation) === ''
                || mb_strlen(trim($limitation)) > 400
                || preg_match('/[\x00-\x1F\x7F]/', $limitation) === 1
            ) {
                throw new AiExplanationPreparationException(
                    'A verified model limitation is invalid.',
                );
            }

            $normalizedLimitations[] = trim($limitation);
        }

        if (
            count($normalizedLimitations)
            !== count(array_unique($normalizedLimitations))
        ) {
            throw new AiExplanationPreparationException(
                'Verified model limitations contain duplicates.',
            );
        }

        $modelName = $this->safeCode($value, 'model_name');

        return [
            'model_run_id' => $this->uuidValue(
                $value,
                'model_run_id',
            ),
            'source_feature_run_id' => $this->uuidValue(
                $value,
                'source_feature_run_id',
            ),
            'model_name' => $modelName,
            'data_classification' => $this->classificationValue(
                $value,
                'data_classification',
            ),
            'limitations' => $normalizedLimitations,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function arrayValue(
        array $source,
        string $key,
    ): array {
        $value = $source[$key] ?? null;

        if (! is_array($value)) {
            throw new AiExplanationPreparationException(
                "Verified field [{$key}] is missing.",
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function stringValue(
        array $source,
        string $key,
        int $maximumLength,
    ): string {
        $value = $source[$key] ?? null;

        if (
            ! is_string($value)
            || trim($value) === ''
            || mb_strlen(trim($value)) > $maximumLength
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
        ) {
            throw new AiExplanationPreparationException(
                "Verified field [{$key}] is invalid.",
            );
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function safeCode(
        array $source,
        string $key,
    ): string {
        $value = $this->stringValue($source, $key, 100);

        if (
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._:\/+\-]{0,99}$/',
                $value,
            ) !== 1
        ) {
            throw new AiExplanationPreparationException(
                "Verified code [{$key}] is invalid.",
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function uuidValue(
        array $source,
        string $key,
    ): string {
        $value = $this->stringValue($source, $key, 100);

        if (! Str::isUuid($value)) {
            throw new AiExplanationPreparationException(
                "Verified UUID [{$key}] is invalid.",
            );
        }

        return strtolower($value);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function classificationValue(
        array $source,
        string $key,
    ): string {
        $value = $this->stringValue($source, $key, 50);

        if ($value !== 'simulated_prototype') {
            throw new AiExplanationPreparationException(
                'Only simulated-prototype model facts may be explained.',
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function integerValue(
        array $source,
        string $key,
    ): int {
        $value = $source[$key] ?? null;

        if (! is_int($value) || $value < 0) {
            throw new AiExplanationPreparationException(
                "Verified integer [{$key}] is invalid.",
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function nullableIntegerValue(
        array $source,
        string $key,
    ): ?int {
        if (($source[$key] ?? null) === null) {
            return null;
        }

        return $this->integerValue($source, $key);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function numberValue(
        array $source,
        string $key,
        bool $allowNegative = false,
    ): float {
        $value = $source[$key] ?? null;

        if (
            (! is_int($value) && ! is_float($value))
            || ! is_finite((float) $value)
            || (! $allowNegative && (float) $value < 0)
        ) {
            throw new AiExplanationPreparationException(
                "Verified numeric field [{$key}] is invalid.",
            );
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function nullableNumberValue(
        array $source,
        string $key,
    ): ?float {
        if (($source[$key] ?? null) === null) {
            return null;
        }

        return $this->numberValue($source, $key);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function probabilityValue(
        array $source,
        string $key,
    ): float {
        $value = $this->numberValue($source, $key);

        if ($value > 1) {
            throw new AiExplanationPreparationException(
                "Verified probability [{$key}] is outside the allowed range.",
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function booleanValue(
        array $source,
        string $key,
    ): bool {
        $value = $source[$key] ?? null;

        if (! is_bool($value)) {
            throw new AiExplanationPreparationException(
                "Verified Boolean field [{$key}] is invalid.",
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function priorityValue(
        array $source,
        string $key,
    ): string {
        $value = $this->stringValue($source, $key, 20);

        if (
            ! in_array(
                $value,
                ['low', 'medium', 'high', 'critical'],
                true,
            )
        ) {
            throw new AiExplanationPreparationException(
                'The verified maintenance priority is invalid.',
            );
        }

        return $value;
    }
}
