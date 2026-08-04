<?php

namespace App\Http\Requests\Production\Operator;

use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreOperatorProductionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $batch = $this->route(
            'productionBatch'
        );

        if (
            $user === null
            || ! $batch instanceof ProductionBatch
            || ! Gate::forUser($user)
                ->allows('view', $batch)
        ) {
            return false;
        }

        foreach (
            $this->allowedOperatorTypes()
            as $type
        ) {
            if (
                Gate::forUser($user)->allows(
                    'report',
                    [
                        ProductionEvent::class,
                        $type,
                    ]
                )
            ) {
                return true;
            }
        }

        return false;
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

            'production_record_id' => [
                'nullable',
                'integer',
                'exists:production_records,id',
            ],

            'machine_id' => [
                'nullable',
                'integer',
                'exists:machines,id',
            ],

            'event_type' => [
                'required',
                Rule::enum(
                    ProductionEventType::class
                ),
                Rule::in(
                    array_map(
                        static fn (
                            ProductionEventType $type
                        ): string =>
                            $type->value,
                        $this->allowedOperatorTypes()
                    )
                ),
            ],

            'severity' => [
                'required',
                Rule::enum(
                    ProductionEventSeverity::class
                ),
            ],

            'title' => [
                'required',
                'string',
                'max:180',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'started_at' => [
                'required',
                'date',
            ],

            'ended_at' => [
                'nullable',
                'date',
                'after_or_equal:started_at',
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

                $type =
                    ProductionEventType::tryFrom(
                        (string) $this->input(
                            'event_type'
                        )
                    );

                if (
                    $type
                    === ProductionEventType
                        ::MachineIncident
                    && $this->input(
                        'machine_id'
                    ) === null
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'machine_id',
                            'A machine must be selected for a machine incident.'
                        );
                }

                $batch = $this->route(
                    'productionBatch'
                );

                if (
                    ! $batch
                    instanceof ProductionBatch
                ) {
                    return;
                }

                $batch->loadMissing(
                    'productionOrder'
                );

                $recordId =
                    $this->input(
                        'production_record_id'
                    );

                if (
                    $recordId !== null
                    && ! ProductionRecord::query()
                        ->whereKey($recordId)
                        ->where(
                            'production_batch_id',
                            $batch->getKey()
                        )
                        ->exists()
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'production_record_id',
                            'The selected production record does not belong to this batch.'
                        );
                }

                $machineId =
                    $this->input('machine_id');

                if (
                    $machineId !== null
                    && ! Machine::query()
                        ->whereKey($machineId)
                        ->where(
                            'production_line_id',
                            $batch
                                ->productionOrder
                                ->production_line_id
                        )
                        ->exists()
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'machine_id',
                            'The selected machine does not belong to this production line.'
                        );
                }

                $endedAt =
                    $this->input('ended_at');

                if (
                    ! is_string($endedAt)
                    || $endedAt === ''
                ) {
                    return;
                }

                $start =
                    CarbonImmutable::parse(
                        (string) $this->input(
                            'started_at'
                        )
                    );

                $end =
                    CarbonImmutable::parse(
                        $endedAt
                    );

                if (
                    $end->diffInMinutes(
                        $start,
                        true
                    ) > 10080
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'ended_at',
                            'An event cannot span more than seven days.'
                        );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' =>
                $this->trimmedOrNull(
                    'title'
                ),

            'description' =>
                $this->trimmedOrNull(
                    'description'
                ),

            'production_record_id' =>
                $this->emptyToNull(
                    'production_record_id'
                ),

            'machine_id' =>
                $this->emptyToNull(
                    'machine_id'
                ),

            'ended_at' =>
                $this->emptyToNull(
                    'ended_at'
                ),
        ]);
    }

    /**
     * @return list<ProductionEventType>
     */
    private function allowedOperatorTypes(): array
    {
        return [
            ProductionEventType::Downtime,
            ProductionEventType::MachineIncident,
            ProductionEventType::Comment,
        ];
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

    private function emptyToNull(
        string $key
    ): mixed {
        $value = $this->input($key);

        return $value === ''
            ? null
            : $value;
    }
}