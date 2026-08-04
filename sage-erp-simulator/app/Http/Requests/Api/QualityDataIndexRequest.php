<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class QualityDataIndexRequest extends FormRequest
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
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
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

            'batch_number' => [
                'nullable',
                'string',
                'max:180',
            ],

            'lot_number' => [
                'nullable',
                'string',
                'max:180',
            ],

            'inspection_number' => [
                'nullable',
                'string',
                'max:180',
            ],

            'inspection_type' => [
                'nullable',
                'string',
                'max:80',
            ],

            'release_number' => [
                'nullable',
                'string',
                'max:180',
            ],

            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'result' => [
                'nullable',
                'string',
                'in:passed,failed',
            ],

            'test_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'test_category' => [
                'nullable',
                'string',
                'max:80',
            ],

            'test_result' => [
                'nullable',
                'string',
                'in:passed,failed',
            ],

            'decision' => [
                'nullable',
                'string',
                'in:released,blocked,rejected',
            ],

            'warehouse_status' => [
                'nullable',
                'string',
                'in:available,quarantine,rejected',
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