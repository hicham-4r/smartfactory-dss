<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MasterDataIndexRequest extends FormRequest
{
    /**
     * Determine whether the request is authorized.
     *
     * Authorization is handled by the ERP token middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for master-data filters.
     *
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
                'max:120',
            ],

            'updated_since' => [
                'nullable',
                'date',
            ],

            'active' => [
                'nullable',
                'boolean',
            ],

            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'family_code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'format_code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'line_code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'shift_code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'machine_type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'criticality' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }

    /**
     * Return normalized filter values.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = $this->validated();

        $filters['per_page'] = (int) (
            $filters['per_page'] ?? 25
        );

        if ($this->has('active')) {
            $filters['active'] = $this->boolean('active');
        }

        return $filters;
    }
}