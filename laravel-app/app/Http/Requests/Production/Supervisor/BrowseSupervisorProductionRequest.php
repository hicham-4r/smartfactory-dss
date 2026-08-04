<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Enums\PermissionName;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowseSupervisorProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::ViewProductionSupervisorDashboard->value
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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

            'production_line_id' => [
                'nullable',
                'integer',
                'exists:production_lines,id',
            ],

            'shift_id' => [
                'nullable',
                'integer',
                'exists:shifts,id',
            ],

            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'record_search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'record_line_id' => [
                'nullable',
                'integer',
                'exists:production_lines,id',
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

            'event_severity' => [
                'nullable',
                Rule::enum(
                    ProductionEventSeverity::class
                ),
            ],

            'event_line_id' => [
                'nullable',
                'integer',
                'exists:production_lines,id',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $keys = [
            'search',
            'status',
            'production_line_id',
            'shift_id',
            'date_from',
            'date_to',
            'record_search',
            'record_line_id',
            'event_search',
            'event_type',
            'event_severity',
            'event_line_id',
        ];

        $normalized = [];

        foreach ($keys as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$key] =
                $value === ''
                    ? null
                    : $value;
        }

        $this->merge($normalized);
    }
}