<?php

namespace App\Http\Requests\Analytics;

use App\Enums\ERP\ErpMaintenanceType;
use App\Enums\PermissionName;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class BrowseMaintenanceKpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::ViewMaintenanceKpis->value
        ) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],

            'timezone' => [
                'required',
                'string',
                'timezone',
            ],

            'production_line_id' => [
                'nullable',
                'integer',
                'exists:production_lines,id',
            ],

            'machine_id' => [
                'nullable',
                'integer',
                'exists:machines,id',
            ],

            'maintenance_type' => [
                'nullable',
                Rule::in(
                    array_map(
                        static fn (
                            ErpMaintenanceType $type
                        ): string => $type->value,
                        ErpMaintenanceType::cases()
                    )
                ),
            ],

            'downtime_category' => [
                'nullable',
                Rule::in([
                    'planned',
                    'unplanned',
                ]),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (
                Validator $validator
            ): void {
                if (
                    ! $validator->errors()
                        ->has('start_date')
                    && ! $validator->errors()
                        ->has('end_date')
                    && ! $validator->errors()
                        ->has('timezone')
                ) {
                    $this->validateDateRange(
                        $validator
                    );
                }

                if (
                    ! $validator->errors()
                        ->has('production_line_id')
                    && ! $validator->errors()
                        ->has('machine_id')
                ) {
                    $this->validateMachineLine(
                        $validator
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $defaultTimezone = (string) config(
            'analytics.default_timezone',
            'Africa/Casablanca'
        );

        $requestedTimezone =
            $this->input('timezone');

        $requestedTimezone =
            is_string($requestedTimezone)
                ? trim($requestedTimezone)
                : '';

        $timezoneForDefaults = in_array(
            $requestedTimezone,
            timezone_identifiers_list(),
            true
        )
            ? $requestedTimezone
            : $defaultTimezone;

        $today = CarbonImmutable::now(
            $timezoneForDefaults
        );

        $normalized = [
            'start_date' =>
                $this->normalizeString(
                    $this->input('start_date')
                ) ?? $today
                    ->startOfMonth()
                    ->toDateString(),

            'end_date' =>
                $this->normalizeString(
                    $this->input('end_date')
                ) ?? $today->toDateString(),

            'timezone' =>
                $requestedTimezone !== ''
                    ? $requestedTimezone
                    : $defaultTimezone,
        ];

        foreach (
            [
                'production_line_id',
                'machine_id',
                'maintenance_type',
                'downtime_category',
            ] as $key
        ) {
            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$key] =
                $value === ''
                    ? null
                    : $value;
        }

        $this->merge($normalized);
    }

    private function validateDateRange(
        Validator $validator
    ): void {
        $timezone = (string) $this->input(
            'timezone'
        );

        $start = CarbonImmutable::parse(
            (string) $this->input('start_date'),
            $timezone
        )->startOfDay();

        $end = CarbonImmutable::parse(
            (string) $this->input('end_date'),
            $timezone
        )->startOfDay();

        $inclusiveDays = (int) $start
            ->diffInDays($end) + 1;

        $maximumDays = (int) config(
            'analytics.maximum_range_days',
            366
        );

        if ($inclusiveDays > $maximumDays) {
            $validator->errors()->add(
                'end_date',
                "The selected period may not exceed {$maximumDays} days."
            );
        }
    }

    private function validateMachineLine(
        Validator $validator
    ): void {
        $lineId = $this->input(
            'production_line_id'
        );

        $machineId = $this->input(
            'machine_id'
        );

        if (
            $lineId === null
            || $lineId === ''
            || $machineId === null
            || $machineId === ''
        ) {
            return;
        }

        $matches = DB::table('machines')
            ->where('id', (int) $machineId)
            ->where(
                'production_line_id',
                (int) $lineId
            )
            ->exists();

        if (! $matches) {
            $validator->errors()->add(
                'machine_id',
                'The selected machine does not belong to the selected production line.'
            );
        }
    }

    private function normalizeString(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}
