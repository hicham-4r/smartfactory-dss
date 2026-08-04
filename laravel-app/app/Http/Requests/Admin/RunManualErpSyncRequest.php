<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RunManualErpSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName
                ::RunManualSynchronization
                ->value
        ) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => [
                'required',
                'integer',
                Rule::in([
                    25,
                    50,
                    100,
                    200,
                ]),
            ],

            'max_pages' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array{
     *     per_page: int,
     *     max_pages: int
     * }
     */
    public function synchronizationOptions(): array
    {
        $validated =
            $this->validated();

        return [
            'per_page' =>
                (int) $validated[
                    'per_page'
                ],

            'max_pages' =>
                (int) $validated[
                    'max_pages'
                ],
        ];
    }
}
