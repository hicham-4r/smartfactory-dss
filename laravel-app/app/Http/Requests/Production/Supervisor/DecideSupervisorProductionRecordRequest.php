<?php

namespace App\Http\Requests\Production\Supervisor;

use App\Enums\Production\ProductionValidationDecision;
use App\Models\ProductionRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class DecideSupervisorProductionRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $record = $this->route(
            'productionRecord'
        );

        if (
            $user === null
            || ! $record instanceof ProductionRecord
        ) {
            return false;
        }

        $decision =
            ProductionValidationDecision::tryFrom(
                (string) $this->input(
                    'decision'
                )
            );

        return match ($decision) {
            ProductionValidationDecision::Validated =>
                Gate::forUser($user)->allows(
                    'validate',
                    $record
                ),

            ProductionValidationDecision::Rejected =>
                Gate::forUser($user)->allows(
                    'reject',
                    $record
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
            'decision' => [
                'required',
                Rule::enum(
                    ProductionValidationDecision::class
                ),
            ],

            'reason' => [
                Rule::requiredIf(
                    fn (): bool =>
                        $this->input('decision')
                        === ProductionValidationDecision
                            ::Rejected
                            ->value
                ),
                'nullable',
                'string',
                'max:2000',
            ],

            'lock_version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            $reason = trim($reason);
        }

        $this->merge([
            'reason' =>
                $reason === ''
                    ? null
                    : $reason,
        ]);
    }
}