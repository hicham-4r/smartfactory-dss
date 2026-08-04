<?php

namespace App\DTOs\Analytics;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class AnalyticsFilter
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public string $timezone,
        public ?int $productionLineId = null,
        public ?int $productId = null,
        public ?int $productFamilyId = null,
        public ?int $shiftId = null,
        public ?int $machineId = null,
        public ?int $productionOrderId = null,
        public ?string $status = null,
        int $maximumRangeDays = 366,
    ) {
        if (! in_array(
            $timezone,
            DateTimeZone::listIdentifiers(),
            true
        )) {
            throw new InvalidArgumentException(
                'The analytics timezone is invalid.'
            );
        }

        if ($maximumRangeDays < 1) {
            throw new InvalidArgumentException(
                'The analytics maximum range must be positive.'
            );
        }

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException(
                'The analytics end date cannot precede the start date.'
            );
        }

        $inclusiveDays = (int) $startDate
            ->startOfDay()
            ->diffInDays(
                $endDate->startOfDay()
            ) + 1;

        if ($inclusiveDays > $maximumRangeDays) {
            throw new InvalidArgumentException(
                "The analytics date range cannot exceed {$maximumRangeDays} days."
            );
        }
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
            ?? config(
                'analytics.default_timezone',
                'Africa/Casablanca'
            )
        );

        $startDate = CarbonImmutable::parse(
            (string) $data['start_date'],
            $timezone
        )->startOfDay();

        $endDate = CarbonImmutable::parse(
            (string) $data['end_date'],
            $timezone
        )->startOfDay();

        return new self(
            startDate: $startDate,
            endDate: $endDate,
            timezone: $timezone,
            productionLineId: self::nullableInt(
                $data['production_line_id']
                ?? null
            ),
            productId: self::nullableInt(
                $data['product_id']
                ?? null
            ),
            productFamilyId: self::nullableInt(
                $data['product_family_id']
                ?? null
            ),
            shiftId: self::nullableInt(
                $data['shift_id']
                ?? null
            ),
            machineId: self::nullableInt(
                $data['machine_id']
                ?? null
            ),
            productionOrderId: self::nullableInt(
                $data['production_order_id']
                ?? null
            ),
            status: self::nullableString(
                $data['status']
                ?? null
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
            'shift_id' => $this->shiftId,
            'machine_id' => $this->machineId,
            'production_order_id' => $this->productionOrderId,
            'status' => $this->status,
        ];
    }

    private static function nullableInt(
        mixed $value
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function nullableString(
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
