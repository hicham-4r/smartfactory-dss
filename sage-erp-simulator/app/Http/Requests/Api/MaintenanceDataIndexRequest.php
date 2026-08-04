<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceDataIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],

            'search' => [
                'nullable',
                'string',
                'max:180',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'updated_since' => [
                'nullable',
                'date',
            ],

            'machine_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'line_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'shift_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'event_number' => [
                'nullable',
                'string',
                'max:200',
            ],

            'status_event_number' => [
                'nullable',
                'string',
                'max:200',
            ],

            'maintenance_number' => [
                'nullable',
                'string',
                'max:200',
            ],

            'category' => [
                'nullable',
                'string',
                'in:planned,unplanned',
            ],

            'downtime_type' => [
                'nullable',
                'string',
                'in:breakdown,planned_maintenance,cleaning,changeover,material_shortage,quality_hold,utility_failure',
            ],

            'status_code' => [
                'nullable',
                'string',
                'in:running,stopped,maintenance,cleaning,setup,idle',
            ],

            'maintenance_type' => [
                'nullable',
                'string',
                'in:preventive,corrective',
            ],

            'priority' => [
                'nullable',
                'string',
                'in:urgent,high,normal,low',
            ],

            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'reason_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'failure_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_late_arrival' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->validated();

        $filters['per_page'] = (int) (
            $filters['per_page'] ?? 25
        );

        if ($this->has('is_late_arrival')) {
            $filters['is_late_arrival'] = $this->boolean(
                'is_late_arrival'
            );
        }

        return $filters;
    }
}