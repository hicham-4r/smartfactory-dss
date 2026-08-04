<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreSupervisorProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $order = $this->route(
            'productionOrder'
        );

        return $user !== null
            && $order instanceof ProductionOrder
            && Gate::forUser($user)->allows(
                'create',
                [
                    ProductionBatch::class,
                    $order,
                ]
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'planned_quantity' => [
                'required',
                'decimal:0,3',
                'gt:0',
                'max:9999999999999.999',
            ],

            'scheduled_start_at' => [
                'nullable',
                'date',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'scheduled_start_at' =>
                $this->input(
                    'scheduled_start_at'
                ) === ''
                    ? null
                    : $this->input(
                        'scheduled_start_at'
                    ),
        ]);
    }
}