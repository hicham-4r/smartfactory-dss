<?php

namespace App\Enums\ERP;

enum ErpResource: string
{
    case ProductFamilies = 'product_families';
    case Products = 'products';
    case ProductionLines = 'production_lines';
    case Machines = 'machines';
    case Shifts = 'shifts';
    case Operators = 'operators';
    case OperatorAssignments = 'operator_assignments';

    case WorkOrders = 'work_orders';
    case Batches = 'batches';
    case MachineRuns = 'machine_runs';
    case RunLogs = 'run_logs';

    case DowntimeEvents = 'downtime_events';
    case MachineStatusEvents = 'machine_status_events';
    case MaintenanceHistory = 'maintenance_history';

    case Inspections = 'inspections';
    case Nonconformities = 'nonconformities';
    case FinishedLots = 'finished_lots';

    public function domain(): string
    {
        return match ($this) {
            self::ProductFamilies,
            self::Products,
            self::ProductionLines,
            self::Machines,
            self::Shifts,
            self::Operators,
            self::OperatorAssignments => 'master_data',

            self::WorkOrders,
            self::Batches,
            self::MachineRuns,
            self::RunLogs => 'production',

            self::DowntimeEvents,
            self::MachineStatusEvents,
            self::MaintenanceHistory => 'maintenance',

            self::Inspections,
            self::Nonconformities,
            self::FinishedLots => 'quality',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ProductFamilies => 'Product families',
            self::Products => 'Products',
            self::ProductionLines => 'Production lines',
            self::Machines => 'Machines',
            self::Shifts => 'Shifts',
            self::Operators => 'Operators',
            self::OperatorAssignments => 'Operator assignments',
            self::WorkOrders => 'Work orders',
            self::Batches => 'Batches',
            self::MachineRuns => 'Machine runs',
            self::RunLogs => 'Run logs',
            self::DowntimeEvents => 'Downtime events',
            self::MachineStatusEvents => 'Machine status events',
            self::MaintenanceHistory => 'Maintenance history',
            self::Inspections => 'Inspections',
            self::Nonconformities => 'Nonconformities',
            self::FinishedLots => 'Finished lots',
        };
    }
}