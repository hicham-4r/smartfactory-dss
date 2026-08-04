<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class BrowseMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName
                ::ViewAdministratorDashboard
                ->value
        ) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => [
                'nullable',
                'string',
                'max:100',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    'active',
                    'inactive',
                ]),
            ],

            'source_system' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
            ],

            'product_family_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'product_families',
                    'id'
                ),
            ],

            'production_line_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'production_lines',
                    'id'
                ),
            ],

            'shift_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'shifts',
                    'id'
                ),
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    15,
                    25,
                    50,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sourceSystem = $this->input(
            'source_system'
        );

        $this->merge([
            'q' => $this->nullableTrimmed(
                $this->input('q')
            ),

            'status' => $this->input(
                'status',
                'all'
            ),

            'source_system' =>
                is_string($sourceSystem)
                    ? Str::lower(
                        trim($sourceSystem)
                    )
                    : null,

            'per_page' => $this->input(
                'per_page',
                15
            ),
        ]);
    }

    /**
     * Return normalized, validated filter data.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' =>
                $validated['q'] ?? null,

            'status' =>
                $validated['status'] ?? 'all',

            'source_system' =>
                $validated['source_system'] ?? null,

            'product_family_id' =>
                isset(
                    $validated[
                        'product_family_id'
                    ]
                )
                    ? (int) $validated[
                        'product_family_id'
                    ]
                    : null,

            'production_line_id' =>
                isset(
                    $validated[
                        'production_line_id'
                    ]
                )
                    ? (int) $validated[
                        'production_line_id'
                    ]
                    : null,

            'shift_id' =>
                isset($validated['shift_id'])
                    ? (int) $validated['shift_id']
                    : null,

            'per_page' =>
                (int) (
                    $validated['per_page']
                    ?? 15
                ),
        ];
    }

    private function nullableTrimmed(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}