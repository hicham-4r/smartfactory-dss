<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

final class AutomaticMaintenanceRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'machine_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'prediction_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'model_run_id' => [
                'nullable',
                'uuid',
            ],
        ];
    }
}
