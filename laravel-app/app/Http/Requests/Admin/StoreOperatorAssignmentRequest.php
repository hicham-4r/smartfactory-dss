<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

final class StoreOperatorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::UpdateUsers->value
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'production_line_id' => [
                'required',
                'integer',
                'exists:production_lines,id',
            ],

            'shift_id' => [
                'required',
                'integer',
                'exists:shifts,id',
            ],

            'starts_on' => [
                'required',
                'date_format:Y-m-d',
            ],

            'ends_on' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:starts_on',
            ],

            'is_primary' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ends_on' =>
                $this->emptyToNull(
                    $this->input('ends_on')
                ),

            'is_primary' =>
                $this->boolean(
                    'is_primary'
                ),
        ]);
    }

    private function emptyToNull(
        mixed $value
    ): mixed {
        return is_string($value)
            && trim($value) === ''
                ? null
                : $value;
    }
}
