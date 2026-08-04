<?php

namespace App\Http\Requests\Production\Operator;

use App\Enums\PermissionName;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowseOperatorProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName
                ::ViewAssignedProductionOrders
                ->value
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'status' => [
                'nullable',
                Rule::enum(
                    ProductionOrderStatus::class
                ),
            ],

            'record_search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'record_status' => [
                'nullable',
                Rule::enum(
                    ProductionRecordStatus::class
                ),
            ],

            'event_search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'event_type' => [
                'nullable',
                Rule::enum(
                    ProductionEventType::class
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_date' =>
                $this->trimmedOrNull(
                    'reference_date'
                ),

            'search' =>
                $this->trimmedOrNull('search'),

            'status' =>
                $this->trimmedOrNull('status'),

            'record_search' =>
                $this->trimmedOrNull(
                    'record_search'
                ),

            'record_status' =>
                $this->trimmedOrNull(
                    'record_status'
                ),

            'event_search' =>
                $this->trimmedOrNull(
                    'event_search'
                ),

            'event_type' =>
                $this->trimmedOrNull(
                    'event_type'
                ),
        ]);
    }

    private function trimmedOrNull(
        string $key
    ): ?string {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}