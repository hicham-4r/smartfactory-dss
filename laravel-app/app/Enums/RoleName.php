<?php

namespace App\Enums;

enum RoleName: string
{
    case Operator = 'operator';

    case ProductionSupervisor = 'production-supervisor';

    case ProductionManager = 'production-manager';

    case MaintenanceManager = 'maintenance-manager';

    case Administrator = 'administrator';

    /**
     * Return every role value.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases()
        );
    }

    /**
     * Human-readable role name for future interfaces and reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::ProductionSupervisor => 'Production Supervisor',
            self::ProductionManager => 'Production Manager',
            self::MaintenanceManager => 'Maintenance Manager',
            self::Administrator => 'Administrator',
        };
    }
}