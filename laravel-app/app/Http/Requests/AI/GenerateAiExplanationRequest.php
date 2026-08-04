<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateAiExplanationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'snapshot_token' => [
                'required',
                'uuid',
            ],
            'language' => [
                'required',
                Rule::in(['en', 'fr']),
            ],
        ];
    }
}
