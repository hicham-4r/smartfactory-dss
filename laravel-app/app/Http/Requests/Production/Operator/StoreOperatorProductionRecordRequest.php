<?php

namespace App\Http\Requests\Production\Operator;

use App\Models\ProductionBatch;
use App\Models\ProductionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class StoreOperatorProductionRecordRequest extends FormRequest
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
                'create',
                [
                    ProductionRecord::class,
                    $batch,
                ]
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shift_id' => [
                'required',
                'integer',
                'exists:shifts,id',
            ],

            'production_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],

            'started_at' => [
                'nullable',
                'date',
            ],

            'ended_at' => [
                'nullable',
                'date',
                'after_or_equal:started_at',
            ],

            'produced_quantity' => [
                'required',
                'decimal:0,3',
                'min:0',
                'max:9999999999999.999',
            ],

            'good_quantity' => [
                'required',
                'decimal:0,3',
                'min:0',
                'max:9999999999999.999',
            ],

            'rejected_quantity' => [
                'required',
                'decimal:0,3',
                'min:0',
                'max:9999999999999.999',
            ],

            'runtime_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:1440',
            ],

            'downtime_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:1440',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (
                Validator $validator
            ): void {
                if (
                    $validator
                        ->errors()
                        ->isNotEmpty()
                ) {
                    return;
                }

                $produced =
                    $this->quantityToMilliUnits(
                        $this->input(
                            'produced_quantity'
                        )
                    );

                $good =
                    $this->quantityToMilliUnits(
                        $this->input(
                            'good_quantity'
                        )
                    );

                $rejected =
                    $this->quantityToMilliUnits(
                        $this->input(
                            'rejected_quantity'
                        )
                    );

                if (
                    $produced
                    !== $good + $rejected
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'produced_quantity',
                            'Produced quantity must equal good quantity plus rejected quantity.'
                        );
                }

                $startedAt =
                    $this->input('started_at');

                $endedAt =
                    $this->input('ended_at');

                if (
                    ! is_string($startedAt)
                    || $startedAt === ''
                    || ! is_string($endedAt)
                    || $endedAt === ''
                ) {
                    return;
                }

                $start =
                    CarbonImmutable::parse(
                        $startedAt
                    );

                $end =
                    CarbonImmutable::parse(
                        $endedAt
                    );

                $elapsedMinutes = (int) floor(
                    (
                        $end->getTimestamp()
                        - $start->getTimestamp()
                    ) / 60
                );

                $recordedMinutes =
                    (int) $this->input(
                        'runtime_minutes'
                    )
                    + (int) $this->input(
                        'downtime_minutes'
                    );

                if (
                    $recordedMinutes
                    > $elapsedMinutes
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'runtime_minutes',
                            'Runtime plus downtime cannot exceed the recorded timeline.'
                        );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' =>
                $this->trimmedOrNull(
                    'notes'
                ),
        ]);
    }

    private function quantityToMilliUnits(
        mixed $quantity
    ): int {
        return (int) round(
            ((float) $quantity) * 1000
        );
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