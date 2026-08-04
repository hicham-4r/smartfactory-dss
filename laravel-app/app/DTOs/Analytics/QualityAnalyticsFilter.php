<?php

namespace App\DTOs\Analytics;

use App\Enums\ERP\ErpFinishedLotStatus;
use App\Enums\ERP\ErpInspectionResult;
use App\Enums\ERP\ErpNonconformitySeverity;
use App\Enums\ERP\ErpNonconformityStatus;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class QualityAnalyticsFilter
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public string $timezone,
        public ?int $productionLineId = null,
        public ?int $productId = null,
        public ?int $productFamilyId = null,
        public ?string $inspectionResult = null,
        public ?string $lotStatus = null,
        public ?string $nonconformitySeverity = null,
        public ?string $nonconformityStatus = null,
        public ?string $lotNumber = null,
        int $maximumRangeDays = 366,
    ) {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException(
                'The quality analytics timezone is invalid.'
            );
        }

        if ($maximumRangeDays < 1) {
            throw new InvalidArgumentException(
                'The quality analytics maximum range must be positive.'
            );
        }

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException(
                'The quality analytics end date cannot precede the start date.'
            );
        }

        $inclusiveDays = (int) $startDate
            ->startOfDay()
            ->diffInDays($endDate->startOfDay()) + 1;

        if ($inclusiveDays > $maximumRangeDays) {
            throw new InvalidArgumentException(
                "The quality analytics date range cannot exceed {$maximumRangeDays} days."
            );
        }

        $this->assertEnumValue(
            value: $inspectionResult,
            enumClass: ErpInspectionResult::class,
            message: 'The inspection-result filter is invalid.',
        );

        $this->assertEnumValue(
            value: $lotStatus,
            enumClass: ErpFinishedLotStatus::class,
            message: 'The finished-lot status filter is invalid.',
        );

        $this->assertEnumValue(
            value: $nonconformitySeverity,
            enumClass: ErpNonconformitySeverity::class,
            message: 'The nonconformity-severity filter is invalid.',
        );

        $this->assertEnumValue(
            value: $nonconformityStatus,
            enumClass: ErpNonconformityStatus::class,
            message: 'The nonconformity-status filter is invalid.',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromValidated(
        array $data,
        int $maximumRangeDays = 366
    ): self {
        $timezone = (string) (
            $data['timezone']
            ?? config('analytics.default_timezone', 'Africa/Casablanca')
        );

        return new self(
            startDate: CarbonImmutable::parse(
                (string) $data['start_date'],
                $timezone
            )->startOfDay(),
            endDate: CarbonImmutable::parse(
                (string) $data['end_date'],
                $timezone
            )->startOfDay(),
            timezone: $timezone,
            productionLineId: self::nullableInt(
                $data['production_line_id'] ?? null
            ),
            productId: self::nullableInt(
                $data['product_id'] ?? null
            ),
            productFamilyId: self::nullableInt(
                $data['product_family_id'] ?? null
            ),
            inspectionResult: self::nullableString(
                $data['inspection_result'] ?? null
            ),
            lotStatus: self::nullableString(
                $data['lot_status'] ?? null
            ),
            nonconformitySeverity: self::nullableString(
                $data['nonconformity_severity'] ?? null
            ),
            nonconformityStatus: self::nullableString(
                $data['nonconformity_status'] ?? null
            ),
            lotNumber: self::nullableString(
                $data['lot_number'] ?? null
            ),
            maximumRangeDays: $maximumRangeDays,
        );
    }

    public function startDateString(): string
    {
        return $this->startDate->toDateString();
    }

    public function endDateString(): string
    {
        return $this->endDate->toDateString();
    }

    public function utcStart(): CarbonImmutable
    {
        return $this->startDate
            ->setTimezone($this->timezone)
            ->startOfDay()
            ->utc();
    }

    public function utcEndExclusive(): CarbonImmutable
    {
        return $this->endDate
            ->setTimezone($this->timezone)
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDateString(),
            'end_date' => $this->endDateString(),
            'timezone' => $this->timezone,
            'production_line_id' => $this->productionLineId,
            'product_id' => $this->productId,
            'product_family_id' => $this->productFamilyId,
            'inspection_result' => $this->inspectionResult,
            'lot_status' => $this->lotStatus,
            'nonconformity_severity' => $this->nonconformitySeverity,
            'nonconformity_status' => $this->nonconformityStatus,
            'lot_number' => $this->lotNumber,
        ];
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     */
    private function assertEnumValue(
        ?string $value,
        string $enumClass,
        string $message,
    ): void {
        if ($value !== null && $enumClass::tryFrom($value) === null) {
            throw new InvalidArgumentException($message);
        }
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === ''
            ? null
            : (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
