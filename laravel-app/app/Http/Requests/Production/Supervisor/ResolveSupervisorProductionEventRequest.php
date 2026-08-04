<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Models\ProductionEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ResolveSupervisorProductionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $event = $this->route(
            'productionEvent'
        );

        return $user !== null
            && $event instanceof ProductionEvent
            && Gate::forUser($user)->allows(
                'resolve',
                $event
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}