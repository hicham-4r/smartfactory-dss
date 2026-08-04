<?php

namespace App\Http\Requests\Analytics;

use App\Enums\ERP\ErpFinishedLotStatus;
use App\Enums\ERP\ErpInspectionResult;
use App\Enums\ERP\ErpNonconformitySeverity;
use App\Enums\ERP\ErpNonconformityStatus;
use App\Enums\PermissionName;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class BrowseQualityKpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            PermissionName::ViewProductionKpis->value
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'timezone' => ['required', 'string', 'timezone'],
            'production_line_id' => [
                'nullable',
                'integer',
                'exists:production_lines,id',
            ],
            'product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'product_family_id' => [
                'nullable',
                'integer',
                'exists:product_families,id',
            ],
            'inspection_result' => [
                'nullable',
                Rule::enum(ErpInspectionResult::class),
            ],
            'lot_status' => [
                'nullable',
                Rule::enum(ErpFinishedLotStatus::class),
            ],
            'nonconformity_severity' => [
                'nullable',
                Rule::enum(ErpNonconformitySeverity::class),
            ],
            'nonconformity_status' => [
                'nullable',
                Rule::enum(ErpNonconformityStatus::class),
            ],
            'lot_number' => [
                'nullable',
                'string',
                'max:120',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    ! $validator->errors()->has('start_date')
                    && ! $validator->errors()->has('end_date')
                    && ! $validator->errors()->has('timezone')
                ) {
                    $this->validateDateRange($validator);
                }

                if (
                    ! $validator->errors()->has('product_id')
                    && ! $validator->errors()->has('product_family_id')
                ) {
                    $this->validateProductFamily($validator);
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
        $requestedTimezone = $this->input('timezone');
        $requestedTimezone = is_string($requestedTimezone)
            ? trim($requestedTimezone)
            : '';
        $timezoneForDefaults = in_array(
            $requestedTimezone,
            timezone_identifiers_list(),
            true
        )
            ? $requestedTimezone
            : $defaultTimezone;
        $today = CarbonImmutable::now($timezoneForDefaults);

        $normalized = [
            'start_date' => $this->normalizeString(
                $this->input('start_date')
            ) ?? $today->startOfMonth()->toDateString(),
            'end_date' => $this->normalizeString(
                $this->input('end_date')
            ) ?? $today->toDateString(),
            'timezone' => $requestedTimezone !== ''
                ? $requestedTimezone
                : $defaultTimezone,
        ];

        foreach (
            [
                'production_line_id',
                'product_id',
                'product_family_id',
                'inspection_result',
                'lot_status',
                'nonconformity_severity',
                'nonconformity_status',
                'lot_number',
            ] as $key
        ) {
            $value = $this->input($key);
            $value = is_string($value) ? trim($value) : $value;
            $normalized[$key] = $value === '' ? null : $value;
        }

        $this->merge($normalized);
    }

    private function validateDateRange(Validator $validator): void
    {
        $timezone = (string) $this->input('timezone');
        $start = CarbonImmutable::parse(
            (string) $this->input('start_date'),
            $timezone
        )->startOfDay();
        $end = CarbonImmutable::parse(
            (string) $this->input('end_date'),
            $timezone
        )->startOfDay();
        $inclusiveDays = (int) $start->diffInDays($end) + 1;
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

    private function validateProductFamily(Validator $validator): void
    {
        $productId = $this->input('product_id');
        $familyId = $this->input('product_family_id');

        if (
            $productId === null
            || $productId === ''
            || $familyId === null
            || $familyId === ''
        ) {
            return;
        }

        if (
            ! DB::table('products')
                ->where('id', (int) $productId)
                ->where('product_family_id', (int) $familyId)
                ->exists()
        ) {
            $validator->errors()->add(
                'product_id',
                'The selected product does not belong to the selected family.'
            );
        }
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
