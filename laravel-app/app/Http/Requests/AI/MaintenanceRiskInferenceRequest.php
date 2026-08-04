<?php

namespace App\Http\Requests\AI;

use App\Http\Requests\AI\Concerns\CastsInferenceInputs;
use Illuminate\Foundation\Http\FormRequest;

final class MaintenanceRiskInferenceRequest extends FormRequest
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
                'days_observed',
                'status_event_count_7d',
                'fault_status_event_count_7d',
                'running_minutes_7d',
                'fault_minutes_7d',
                'downtime_event_count_7d',
                'unplanned_downtime_event_count_7d',
                'total_downtime_minutes_7d',
                'unplanned_downtime_minutes_7d',
                'maintenance_event_count_30d',
                'preventive_maintenance_count_30d',
                'corrective_maintenance_count_30d',
                'maintenance_downtime_minutes_30d',
            ],
            nullableIntegerFields: [
                'days_since_last_failure',
                'days_since_last_maintenance',
            ],
            booleanFields: ['is_critical'],
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
        $countRules = [
            'required',
            'integer',
            'min:0',
            'max:10000000',
        ];

        return [
            'prediction_date' => ['required', 'date_format:Y-m-d'],
            'model_run_id' => ['nullable', 'uuid'],
            'features' => ['required', 'array'],
            'features.production_line_code' => $codeRules,
            'features.machine_code' => $codeRules,
            'features.machine_type' => [
                'required',
                'string',
                'max:100',
            ],
            'features.is_critical' => ['required', 'boolean'],
            'features.days_observed' => $countRules,
            'features.status_event_count_7d' => $countRules,
            'features.fault_status_event_count_7d' => $countRules,
            'features.running_minutes_7d' => $countRules,
            'features.fault_minutes_7d' => $countRules,
            'features.downtime_event_count_7d' => $countRules,
            'features.unplanned_downtime_event_count_7d' => $countRules,
            'features.total_downtime_minutes_7d' => $countRules,
            'features.unplanned_downtime_minutes_7d' => $countRules,
            'features.maintenance_event_count_30d' => $countRules,
            'features.preventive_maintenance_count_30d' => $countRules,
            'features.corrective_maintenance_count_30d' => $countRules,
            'features.maintenance_downtime_minutes_30d' => $countRules,
            'features.days_since_last_failure' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000',
            ],
            'features.days_since_last_maintenance' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000',
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
