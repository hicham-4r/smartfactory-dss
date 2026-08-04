<?php

namespace App\Enums\ERP;

enum ErpSyncGroup: string
{
    case Catalog = 'catalog';

    case FactoryMaster =
        'factory_master';

    case ProductionExecution =
        'production_execution';

    case Maintenance =
        'maintenance';

    case Quality =
        'quality';

    public function inputName(): string
    {
        return str_replace(
            '_',
            '-',
            $this->value
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Catalog =>
                'Catalog master data',

            self::FactoryMaster =>
                'Factory master data',

            self::ProductionExecution =>
                'Production execution',

            self::Maintenance =>
                'Maintenance and downtime',

            self::Quality =>
                'Quality and lot release',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Catalog =>
                'Product families and products.',

            self::FactoryMaster =>
                'Production lines, machines, shifts, operators and assignments.',

            self::ProductionExecution =>
                'Work orders, batches, machine runs and run logs.',

            self::Maintenance =>
                'Machine status, downtime and maintenance history.',

            self::Quality =>
                'Finished lots, inspections and nonconformities.',
        };
    }
}