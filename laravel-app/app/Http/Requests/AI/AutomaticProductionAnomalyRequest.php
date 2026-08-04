<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

final class AutomaticProductionAnomalyRequest extends FormRequest
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
            'production_record_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'model_run_id' => [
                'nullable',
                'uuid',
            ],
        ];
    }
}
