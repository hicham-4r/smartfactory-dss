<?php

namespace App\Http\Requests\AI;

use App\Http\Requests\AI\Concerns\CastsInferenceInputs;
use Illuminate\Foundation\Http\FormRequest;

final class ProductionForecastInferenceRequest extends FormRequest
{
    use CastsInferenceInputs;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->castFeatureInputs(
            integerFields: [
                'days_of_history',
                'rolling_observation_count_7d',
                'day_of_week',
                'month',
                'runtime_minutes_lag_1d',
                'downtime_minutes_lag_1d',
            ],
            floatFields: [
                'good_quantity_lag_1d',
                'good_quantity_mean_7d',
                'good_quantity_min_7d',
                'good_quantity_max_7d',
                'produced_quantity_lag_1d',
                'target_quantity_lag_1d',
            ],
            nullableFloatFields: [
                'good_quantity_lag_7d',
                'rejection_rate_lag_1d',
                'achievement_rate_lag_1d',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prediction_date' => ['required', 'date_format:Y-m-d'],
            'model_run_id' => ['nullable', 'uuid'],
            'features' => ['required', 'array'],
            'features.production_line_code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
            'features.quantity_unit' => [
                'required',
                'string',
                'max:100',
            ],
            'features.days_of_history' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],
            'features.rolling_observation_count_7d' => [
                'required',
                'integer',
                'between:1,7',
            ],
            'features.day_of_week' => [
                'required',
                'integer',
                'between:0,6',
            ],
            'features.month' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'features.good_quantity_lag_1d' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.good_quantity_lag_7d' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.good_quantity_mean_7d' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.good_quantity_min_7d' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.good_quantity_max_7d' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.produced_quantity_lag_1d' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.target_quantity_lag_1d' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.runtime_minutes_lag_1d' => [
                'required',
                'integer',
                'min:0',
                'max:10000000',
            ],
            'features.downtime_minutes_lag_1d' => [
                'required',
                'integer',
                'min:0',
                'max:10000000',
            ],
            'features.rejection_rate_lag_1d' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'features.achievement_rate_lag_1d' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inferencePayload(): array
    {
        $validated = $this->validated();

        return [
            'prediction_date' => $validated['prediction_date'],
            'model_run_id' => $validated['model_run_id'] ?? null,
            'features' => $validated['features'],
        ];
    }
}
