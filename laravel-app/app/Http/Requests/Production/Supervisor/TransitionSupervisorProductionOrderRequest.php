<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Enums\Production\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class TransitionSupervisorProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $order = $this->route(
            'productionOrder'
        );

        if (
            $user === null
            || ! $order instanceof ProductionOrder
        ) {
            return false;
        }

        $target =
            ProductionOrderStatus::tryFrom(
                (string) $this->input(
                    'target_status'
                )
            );

        return match ($target) {
            ProductionOrderStatus::Planned =>
                Gate::forUser($user)->allows(
                    'update',
                    $order
                ),

            ProductionOrderStatus::Released =>
                Gate::forUser($user)->allows(
                    'release',
                    $order
                ),

            ProductionOrderStatus::Cancelled =>
                Gate::forUser($user)->allows(
                    'cancel',
                    $order
                ),

            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_status' => [
                'required',
                Rule::in([
                    ProductionOrderStatus::Planned->value,
                    ProductionOrderStatus::Released->value,
                    ProductionOrderStatus::Cancelled->value,
                ]),
            ],

            'lock_version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}