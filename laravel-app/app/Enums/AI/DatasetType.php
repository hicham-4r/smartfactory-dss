<?php

namespace App\Enums\AI;

use InvalidArgumentException;

enum DatasetType: string
{
    case ProductionRecords = 'production_records';
    case DowntimeEvents = 'downtime_events';
    case MachineStatusEvents = 'machine_status_events';
    case MaintenanceHistory = 'maintenance_history';
    case QualityInspections = 'quality_inspections';
    case FinishedLots = 'finished_lots';
    case Nonconformities = 'nonconformities';

    public function filename(): string
    {
        return $this->value.'.csv';
    }

    public function label(): string
    {
        return match ($this) {
            self::ProductionRecords =>
                'Production records',
            self::DowntimeEvents =>
                'Downtime events',
            self::MachineStatusEvents =>
                'Machine status events',
            self::MaintenanceHistory =>
                'Maintenance history',
            self::QualityInspections =>
                'Quality inspections',
            self::FinishedLots =>
                'Finished lots',
            self::Nonconformities =>
                'Nonconformities',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string =>
                $type->value,
            self::cases()
        );
    }

    /**
     * @return list<self>
     */
    public static function parseList(
        ?string $value
    ): array {
        if (
            $value === null
            || trim($value) === ''
            || strtolower(trim($value)) === 'all'
        ) {
            return self::cases();
        }

        $requested = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (
                            string $item
                        ): string =>
                            strtolower(
                                trim($item)
                            ),
                        explode(',', $value)
                    ),
                    static fn (
                        string $item
                    ): bool => $item !== ''
                )
            )
        );

        if ($requested === []) {
            throw new InvalidArgumentException(
                'At least one dataset type is required.'
            );
        }

        $types = [];

        foreach ($requested as $name) {
            $type = self::tryFrom($name);

            if ($type === null) {
                throw new InvalidArgumentException(
                    'Unknown dataset type ['.$name.']. Allowed values: '
                    .implode(', ', self::values()).'.'
                );
            }

            $types[] = $type;
        }

        /*
         * Preserve the canonical enum order, regardless of the option order,
         * so identical requests produce files and manifests in the same order.
         */
        return array_values(
            array_filter(
                self::cases(),
                static fn (
                    self $type
                ): bool => in_array(
                    $type,
                    $types,
                    true
                )
            )
        );
    }
}
