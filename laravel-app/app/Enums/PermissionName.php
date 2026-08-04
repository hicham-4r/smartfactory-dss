<?php

namespace App\Enums;

enum PermissionName: string
{
    /*
    |--------------------------------------------------------------------------
    | Personal account
    |--------------------------------------------------------------------------
    */

    case ViewOwnProfile = 'account.profile.view';

    case UpdateOwnPassword = 'account.password.update';

    /*
    |--------------------------------------------------------------------------
    | Role-specific dashboards
    |--------------------------------------------------------------------------
    */

    case ViewOperatorDashboard = 'dashboard.operator.view';

    case ViewProductionSupervisorDashboard =
        'dashboard.production-supervisor.view';

    case ViewProductionManagerDashboard =
        'dashboard.production-manager.view';

    case ViewMaintenanceManagerDashboard =
        'dashboard.maintenance-manager.view';

    case ViewAdministratorDashboard =
        'dashboard.administrator.view';

    /*
    |--------------------------------------------------------------------------
    | Operator production workflow
    |--------------------------------------------------------------------------
    */

    case ViewAssignedProductionLine =
        'production.assigned-line.view';

    case ViewAssignedProductionOrders =
        'production.assigned-orders.view';

    case ViewProductionTargets =
        'production.targets.view';

    case CreateProductionRecords =
        'production.records.create';

    case ViewOwnProductionRecords =
        'production.records.view-own';

    case UpdateRecentProductionRecords =
        'production.records.update-recent';

    case ReportDowntime =
        'production.downtime.report';

    case ReportMachineIncident =
        'production.incidents.report';

    case AddProductionEventComment =
        'production.events.comment';

    /*
    |--------------------------------------------------------------------------
    | Production supervision and management
    |--------------------------------------------------------------------------
    */

    case ViewAllProductionRecords =
        'production.records.view-all';

    case ValidateProductionRecords =
        'production.records.validate';

    case RejectProductionRecords =
        'production.records.reject';

    case CompareProductionLines =
        'production.lines.compare';

    case CompareProductionShifts =
        'production.shifts.compare';

    case ViewProductionKpis =
        'production.kpis.view';

    case ViewProductionAnomalies =
        'production.anomalies.view';

    case ViewProductionForecasts =
        'production.forecasts.view';

    case ViewProductionAiExplanations =
        'production.ai-explanations.view';

    case GenerateDailyProductionReports =
        'production.reports.daily.generate';

    case GenerateWeeklyProductionReports =
        'production.reports.weekly.generate';

    case GenerateExecutiveProductionReports =
        'production.reports.executive.generate';

    case ExportProductionReports =
        'production.reports.export';

    /*
    |--------------------------------------------------------------------------
    | Maintenance
    |--------------------------------------------------------------------------
    */

    case ViewMachines =
        'maintenance.machines.view';

    case ViewDowntimeHistory =
        'maintenance.downtime-history.view';

    case CreateMaintenanceRequests =
        'maintenance.requests.create';

    case AssignMaintenanceRequests =
        'maintenance.requests.assign';

    case UpdateMaintenanceRequests =
        'maintenance.requests.update';

    case CloseMaintenanceRequests =
        'maintenance.requests.close';

    case SchedulePreventiveMaintenance =
        'maintenance.preventive.schedule';

    case RecordCorrectiveMaintenance =
        'maintenance.corrective.record';

    case ViewMaintenanceHistory =
        'maintenance.history.view';

    case ViewMaintenanceKpis =
        'maintenance.kpis.view';

    case ViewMaintenanceAnomalies =
        'maintenance.anomalies.view';

    case ViewMaintenanceAiRecommendations =
        'maintenance.ai-recommendations.view';

    case GenerateMaintenanceReports =
        'maintenance.reports.generate';

    /*
    |--------------------------------------------------------------------------
    | User and authorization administration
    |--------------------------------------------------------------------------
    */

    case ViewUsers =
        'administration.users.view';

    case CreateUsers =
        'administration.users.create';

    case UpdateUsers =
        'administration.users.update';

    case ActivateUsers =
        'administration.users.activate';

    case DeactivateUsers =
        'administration.users.deactivate';

    case ResetUserPasswords =
        'administration.users.reset-password';

    case ViewRoles =
        'administration.roles.view';

    case ManageRoles =
        'administration.roles.manage';

    case ViewPermissions =
        'administration.permissions.view';

    case ManagePermissions =
        'administration.permissions.manage';

    /*
    |--------------------------------------------------------------------------
    | Master-data administration
    |--------------------------------------------------------------------------
    */

    case ManageProducts =
        'administration.products.manage';

    case ManageProductionLines =
        'administration.production-lines.manage';

    case ManageMachines =
        'administration.machines.manage';

    case ManageShifts =
        'administration.shifts.manage';

    /*
    |--------------------------------------------------------------------------
    | ERP integration administration
    |--------------------------------------------------------------------------
    */

    case ViewErpConnectorSettings =
        'administration.erp-settings.view';

    case ManageErpConnectorSettings =
        'administration.erp-settings.manage';

    case RunManualSynchronization =
        'administration.synchronization.run';

    case ManageSynchronizationSchedules =
        'administration.synchronization-schedules.manage';

    case ViewSynchronizationLogs =
        'administration.synchronization-logs.view';

    /*
    |--------------------------------------------------------------------------
    | System administration
    |--------------------------------------------------------------------------
    */

    case ManageAiSettings =
        'administration.ai-settings.manage';

    case ManageNotificationSettings =
        'administration.notification-settings.manage';

    case ViewAuditLogs =
        'administration.audit-logs.view';

    case ViewApplicationLogs =
        'administration.application-logs.view';

    case ManageSystemSettings =
        'administration.system-settings.manage';

    case ViewSystemHealth =
        'administration.system-health.view';

    /**
     * Return every permission value.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases()
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Production-order and workflow administration
    |--------------------------------------------------------------------------
    */

    case ViewAllProductionOrders =
        'production.orders.view-all';

    case CreateProductionOrders =
        'production.orders.create';

    case UpdateProductionOrders =
        'production.orders.update';

    case ReleaseProductionOrders =
        'production.orders.release';

    case CancelProductionOrders =
        'production.orders.cancel';

    case CreateProductionBatches =
        'production.batches.create';

    case ManageProductionBatches =
        'production.batches.manage';

    case SubmitProductionRecords =
        'production.records.submit';

    case ViewProductionEvents =
        'production.events.view';

    case ResolveProductionEvents =
        'production.events.resolve';
}