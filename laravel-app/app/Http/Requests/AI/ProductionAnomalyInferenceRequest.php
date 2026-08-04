<?php

namespace App\Http\Requests\AI;

use App\Http\Requests\AI\Concerns\CastsInferenceInputs;
use Illuminate\Foundation\Http\FormRequest;

final class ProductionAnomalyInferenceRequest extends FormRequest
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
                'production_order_priority',
                'runtime_minutes',
                'downtime_minutes',
            ],
            floatFields: [
                'target_quantity',
                'produced_quantity',
                'good_quantity',
                'rejected_quantity',
            ],
            nullableFloatFields: [
                'achievement_ratio',
                'rejection_ratio',
                'good_yield_ratio',
                'throughput_per_hour',
                'downtime_ratio',
            ],
            booleanFields: ['is_validated'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $codeRules = [
            'required',
            'string',
            'max:100',
            'regex:/^[A-Za-z0-9._:-]+$/',
        ];
        $quantityRules = [
            'required',
            'numeric',
            'min:0',
            'max:1000000000000',
        ];

        return [
            'event_time_utc' => ['required', 'date'],
            'model_run_id' => ['nullable', 'uuid'],
            'features' => ['required', 'array'],
            'features.production_line_code' => $codeRules,
            'features.product_family_code' => $codeRules,
            'features.product_code' => $codeRules,
            'features.shift_code' => $codeRules,
            'features.quantity_unit' => [
                'required',
                'string',
                'max:100',
            ],
            'features.production_order_priority' => [
                'required',
                'integer',
                'min:0',
                'max:1000',
            ],
            'features.target_quantity' => $quantityRules,
            'features.produced_quantity' => $quantityRules,
            'features.good_quantity' => $quantityRules,
            'features.rejected_quantity' => $quantityRules,
            'features.runtime_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:10000000',
            ],
            'features.downtime_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:10000000',
            ],
            'features.achievement_ratio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'features.rejection_ratio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'features.good_yield_ratio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'features.throughput_per_hour' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1000000000000',
            ],
            'features.downtime_ratio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'features.is_validated' => [
                'required',
                'boolean',
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
            'event_time_utc' => $validated['event_time_utc'],
            'model_run_id' => $validated['model_run_id'] ?? null,
            'features' => $validated['features'],
        ];
    }
}
