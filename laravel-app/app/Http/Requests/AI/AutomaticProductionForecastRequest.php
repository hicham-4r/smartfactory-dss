<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

final class AutomaticProductionForecastRequest extends FormRequest
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
            'prediction_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'production_line_code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._:-]+$/',
            ],
            'quantity_unit' => [
                'required',
                'string',
                'max:100',
            ],
            'model_run_id' => [
                'nullable',
                'uuid',
            ],
        ];
    }
}
