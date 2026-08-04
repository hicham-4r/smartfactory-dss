<?php

namespace App\DTOs\Dashboard;

use App\Enums\ERP\ErpMaintenanceType;
use App\Enums\Production\ProductionOrderStatus;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class DashboardFilter
{
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public string $timezone,
        public ?int $productionLineId = null,
        public ?int $productId = null,
        public ?int $shiftId = null,
        public ?string $status = null,
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
                'The dashboard timezone is invalid.'
            );
        }

        if ($maximumRangeDays < 1) {
            throw new InvalidArgumentException(
                'The dashboard maximum range must be positive.'
            );
        }

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException(
                'The dashboard end date cannot precede the start date.'
            );
        }

        $inclusiveDays = (int) $startDate
            ->startOfDay()
            ->diffInDays(
                $endDate->startOfDay()
            ) + 1;

        if ($inclusiveDays > $maximumRangeDays) {
            throw new InvalidArgumentException(
                "The dashboard date range cannot exceed {$maximumRangeDays} days."
            );
        }

        if (
            $status !== null
            && ! in_array(
                $status,
                [
                    ProductionOrderStatus::InProgress->value,
                    ProductionOrderStatus::Completed->value,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'The dashboard execution status is invalid.'
            );
        }

        if (
            $maintenanceType !== null
            && ErpMaintenanceType::tryFrom(
                $maintenanceType
            ) === null
        ) {
            throw new InvalidArgumentException(
                'The dashboard maintenance type is invalid.'
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
                'The dashboard downtime category is invalid.'
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
                $data['production_line_id']
                ?? null
            ),

            productId: self::nullableInt(
                $data['product_id']
                ?? null
            ),

            shiftId: self::nullableInt(
                $data['shift_id']
                ?? null
            ),

            status: self::nullableString(
                $data['status']
                ?? null
            ),

            machineId: self::nullableInt(
                $data['machine_id']
                ?? null
            ),

            maintenanceType: self::nullableString(
                $data['maintenance_type']
                ?? null
            ),

            downtimeCategory: self::nullableString(
                $data['downtime_category']
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
     * Production-oriented dashboard and analytics query.
     *
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        $query = $this->periodQuery();

        foreach (
            [
                'production_line_id' => $this->productionLineId,
                'product_id' => $this->productId,
                'shift_id' => $this->shiftId,
                'status' => $this->status,
            ] as $key => $value
        ) {
            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @return array<string, int|string>
     */
    public function toQualityQuery(): array
    {
        $query = $this->periodQuery();

        if ($this->productionLineId !== null) {
            $query['production_line_id'] =
                $this->productionLineId;
        }

        if ($this->productId !== null) {
            $query['product_id'] =
                $this->productId;
        }

        return $query;
    }

    /**
     * @return array<string, int|string>
     */
    public function toMaintenanceQuery(): array
    {
        $query = $this->periodQuery();

        foreach (
            [
                'production_line_id' => $this->productionLineId,
                'machine_id' => $this->machineId,
                'maintenance_type' => $this->maintenanceType,
                'downtime_category' => $this->downtimeCategory,
            ] as $key => $value
        ) {
            if ($value !== null) {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return array_merge(
            $this->toQuery(),
            array_filter(
                [
                    'machine_id' => $this->machineId,
                    'maintenance_type' => $this->maintenanceType,
                    'downtime_category' => $this->downtimeCategory,
                ],
                static fn (mixed $value): bool =>
                    $value !== null
            )
        );
    }

    /**
     * @return array<string, string>
     */
    private function periodQuery(): array
    {
        return [
            'start_date' => $this->startDateString(),
            'end_date' => $this->endDateString(),
            'timezone' => $this->timezone,
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
