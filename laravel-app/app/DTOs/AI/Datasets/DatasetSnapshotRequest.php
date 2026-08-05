<?php

namespace App\DTOs\AI\Datasets;

use App\Enums\AI\DatasetType;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class DatasetSnapshotRequest
{
    /**
     * @var list<DatasetType>
     */
    public array $datasets;

    /**
     * @param list<DatasetType> $datasets
     */
    public function __construct(
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public string $timezone,
        array $datasets,
        public string $sourceSystem =
            'simulated_sage',
        int $maximumRangeDays = 366,
    ) {
        if (! in_array(
            $timezone,
            DateTimeZone::listIdentifiers(),
            true
        )) {
            throw new InvalidArgumentException(
                'The dataset timezone is invalid.'
            );
        }

        if ($maximumRangeDays < 1) {
            throw new InvalidArgumentException(
                'The maximum dataset range must be positive.'
            );
        }

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException(
                'The dataset end date cannot precede the start date.'
            );
        }

        $inclusiveDays = (int) $startDate
            ->startOfDay()
            ->diffInDays(
                $endDate->startOfDay()
            ) + 1;

        if ($inclusiveDays > $maximumRangeDays) {
            throw new InvalidArgumentException(
                "The dataset date range cannot exceed {$maximumRangeDays} days."
            );
        }

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{0,49}$/',
                $sourceSystem
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'The dataset source system is invalid.'
            );
        }

        if ($datasets === []) {
            throw new InvalidArgumentException(
                'At least one dataset type is required.'
            );
        }

        $normalized = [];

        foreach ($datasets as $dataset) {
            if (! $dataset instanceof DatasetType) {
                throw new InvalidArgumentException(
                    'Dataset requests may contain only DatasetType values.'
                );
            }

            $normalized[$dataset->value] = $dataset;
        }

        $ordered = [];

        foreach (DatasetType::cases() as $dataset) {
            if (
                array_key_exists(
                    $dataset->value,
                    $normalized
                )
            ) {
                $ordered[] = $dataset;
            }
        }

        $this->datasets = $ordered;
    }

    public function startDateString(): string
    {
        return $this->startDate
            ->setTimezone($this->timezone)
            ->toDateString();
    }

    public function endDateString(): string
    {
        return $this->endDate
            ->setTimezone($this->timezone)
            ->toDateString();
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
     * @return array<string, mixed>
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
            'utc_start' =>
                $this->utcStart()
                    ->toIso8601String(),
            'utc_end_exclusive' =>
                $this->utcEndExclusive()
                    ->toIso8601String(),
            'source_system' =>
                $this->sourceSystem,
            'datasets' =>
                array_map(
                    static fn (
                        DatasetType $dataset
                    ): string =>
                        $dataset->value,
                    $this->datasets
                ),
        ];
    }
}
