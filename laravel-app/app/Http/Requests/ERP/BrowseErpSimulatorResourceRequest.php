<?php

namespace App\Http\Requests\ERP;

use Illuminate\Foundation\Http\FormRequest;

final class BrowseErpSimulatorResourceRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        /*
         * Authentication is performed by
         * VerifyErpSimulatorToken middleware.
         */
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maximumPageSize = max(
            1,
            min(
                200,
                (int) config(
                    'erp.simulator.maximum_page_size',
                    200
                )
            )
        );

        return [
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:'.$maximumPageSize,
            ],

            'cursor' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'updated_since' => [
                'nullable',
                'date',
            ],

            'source_version' => [
                'nullable',
                'integer',
                'min:0',
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

            'status' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9 _-]+$/',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'product_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'production_line_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'machine_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'shift_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'operator_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'work_order_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'batch_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (
            [
                'cursor',
                'updated_since',
                'date_from',
                'date_to',
                'status',
            ] as $field
        ) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            $values[$field] =
                $value === ''
                    ? null
                    : $value;
        }

        $this->merge($values);
    }
}