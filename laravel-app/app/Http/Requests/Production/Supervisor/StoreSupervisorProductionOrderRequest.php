<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreSupervisorProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && Gate::forUser($user)->allows(
                'create',
                ProductionOrder::class
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'production_line_id' => [
                'required',
                'integer',
                'exists:production_lines,id',
            ],

            'shift_id' => [
                'nullable',
                'integer',
                'exists:shifts,id',
            ],

            'planned_start_at' => [
                'required',
                'date',
            ],

            'planned_end_at' => [
                'nullable',
                'date',
                'after:planned_start_at',
            ],

            'target_quantity' => [
                'required',
                'decimal:0,3',
                'gt:0',
                'max:9999999999999.999',
            ],

            'quantity_unit' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/',
            ],

            'priority' => [
                'required',
                'integer',
                'between:1,5',
            ],

            'instructions' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shift_id' =>
                $this->emptyToNull('shift_id'),

            'planned_end_at' =>
                $this->emptyToNull(
                    'planned_end_at'
                ),

            'quantity_unit' =>
                $this->trimmedOrNull(
                    'quantity_unit'
                ),

            'instructions' =>
                $this->trimmedOrNull(
                    'instructions'
                ),
        ]);
    }

    private function emptyToNull(
        string $key
    ): mixed {
        $value = $this->input($key);

        return $value === ''
            ? null
            : $value;
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