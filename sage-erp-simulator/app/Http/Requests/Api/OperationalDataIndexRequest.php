<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class OperationalDataIndexRequest extends FormRequest
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

            'product_code' => [
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

            'order_number' => [
                'nullable',
                'string',
                'max:120',
            ],

            'batch_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'lot_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'quality_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'machine_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'process_stage_code' => [
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