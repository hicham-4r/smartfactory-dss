<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Enums\Production\ProductionBatchStatus;
use App\Models\ProductionBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class TransitionSupervisorProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $batch = $this->route(
            'productionBatch'
        );

        return $user !== null
            && $batch instanceof ProductionBatch
            && Gate::forUser($user)->allows(
                'transition',
                $batch
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_status' => [
                'required',
                Rule::enum(
                    ProductionBatchStatus::class
                ),
            ],

            'lock_version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}