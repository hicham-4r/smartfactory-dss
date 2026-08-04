<?php

namespace App\DTOs\Analytics;

use App\Enums\ERP\ErpMaintenanceType;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class MaintenanceAnalyticsFilter
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public string $timezone,
        public ?int $productionLineId = null,
        public ?int $machineId = null,
        public ?string $maintenanceType = null,
        public ?string $downtimeCategory = null,
        int $maximumRangeDays = 366,
    ) {
        if (! in_array(
            $timezone,
            DateTimeZone::listIdentifiers(),
            true
        )) {
            throw new InvalidArgumentException(
                'The maintenance analytics timezone is invalid.'
            );
        }

        if ($maximumRangeDays < 1) {
            throw new InvalidArgumentException(
                'The maintenance analytics maximum range must be positive.'
            );
        }

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException(
                'The maintenance analytics end date cannot precede the start date.'
            );
        }

        $inclusiveDays = (int) $startDate
            ->startOfDay()
            ->diffInDays(
                $endDate->startOfDay()
            ) + 1;

        if ($inclusiveDays > $maximumRangeDays) {
            throw new InvalidArgumentException(
                "The maintenance analytics date range cannot exceed {$maximumRangeDays} days."
            );
        }

        if (
            $maintenanceType !== null
            && ErpMaintenanceType::tryFrom(
                $maintenanceType
            ) === null
        ) {
            throw new InvalidArgumentException(
                'The maintenance type filter is invalid.'
            );
        }

        if (
            $downtimeCategory !== null
            && ! in_array(
                $downtimeCategory,
                [
                    'planned',
                    'unplanned',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'The downtime category filter is invalid.'
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

        return new self(
            startDate:
                CarbonImmutable::parse(
                    (string) $data['start_date'],
                    $timezone
                )->startOfDay(),

            endDate:
                CarbonImmutable::parse(
                    (string) $data['end_date'],
                    $timezone
                )->startOfDay(),

            timezone:
                $timezone,

            productionLineId:
                self::nullableInt(
                    $data['production_line_id']
                    ?? null
                ),

            machineId:
                self::nullableInt(
                    $data['machine_id']
                    ?? null
                ),

            maintenanceType:
                self::nullableString(
                    $data['maintenance_type']
                    ?? null
                ),

            downtimeCategory:
                self::nullableString(
                    $data['downtime_category']
                    ?? null
                ),

            maximumRangeDays:
                $maximumRangeDays,
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
            'start_date' =>
                $this->startDateString(),

            'end_date' =>
                $this->endDateString(),

            'timezone' =>
                $this->timezone,

            'production_line_id' =>
                $this->productionLineId,

            'machine_id' =>
                $this->machineId,

            'maintenance_type' =>
                $this->maintenanceType,

            'downtime_category' =>
                $this->downtimeCategory,
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
