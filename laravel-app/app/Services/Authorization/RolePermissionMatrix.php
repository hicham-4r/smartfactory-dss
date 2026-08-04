<?php

namespace App\Services\Authorization;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Support\Str;

final class RolePermissionMatrix
{
    /**
     * Return the normal permission assignment for every fixed role.
     *
     * @return array<string, list<string>>
     */
    public function baseline(): array
    {
        $personalAccountPermissions = [
            PermissionName::ViewOwnProfile->value,
            PermissionName::UpdateOwnPassword->value,
        ];

        return [
            /*
             * Operators work only with their assigned production
             * context and their own production records.
             */
            RoleName::Operator->value => [
                ...$personalAccountPermissions,

                PermissionName::ViewOperatorDashboard->value,

                PermissionName
                    ::ViewAssignedProductionLine
                    ->value,

                PermissionName
                    ::ViewAssignedProductionOrders
                    ->value,

                PermissionName::ViewProductionTargets->value,

                PermissionName::CreateProductionRecords->value,

                PermissionName
                    ::ViewOwnProductionRecords
                    ->value,

                PermissionName
                    ::UpdateRecentProductionRecords
                    ->value,

                PermissionName::SubmitProductionRecords->value,

                PermissionName::ReportDowntime->value,

                PermissionName
                    ::ReportMachineIncident
                    ->value,

                PermissionName
                    ::AddProductionEventComment
                    ->value,

                PermissionName::ViewProductionEvents->value,
            ],

            /*
             * Production supervisors execute the operational
             * production workflow and review operator records.
             */
            RoleName::ProductionSupervisor->value => [
                ...$personalAccountPermissions,

                PermissionName
                    ::ViewProductionSupervisorDashboard
                    ->value,

                PermissionName::ViewProductionTargets->value,

                PermissionName
                    ::ViewAllProductionOrders
                    ->value,

                PermissionName
                    ::CreateProductionOrders
                    ->value,

                PermissionName
                    ::UpdateProductionOrders
                    ->value,

                PermissionName
                    ::ReleaseProductionOrders
                    ->value,

                PermissionName
                    ::CancelProductionOrders
                    ->value,

                PermissionName
                    ::CreateProductionBatches
                    ->value,

                PermissionName
                    ::ManageProductionBatches
                    ->value,

                PermissionName
                    ::CreateProductionRecords
                    ->value,

                PermissionName
                    ::SubmitProductionRecords
                    ->value,

                PermissionName
                    ::ViewAllProductionRecords
                    ->value,

                PermissionName
                    ::ValidateProductionRecords
                    ->value,

                PermissionName
                    ::RejectProductionRecords
                    ->value,

                PermissionName::ViewProductionEvents->value,

                PermissionName
                    ::ResolveProductionEvents
                    ->value,

                PermissionName::CompareProductionLines->value,

                PermissionName::CompareProductionShifts->value,

                PermissionName::ViewProductionKpis->value,

                PermissionName
                    ::ViewProductionAnomalies
                    ->value,

                PermissionName
                    ::ViewProductionForecasts
                    ->value,

                PermissionName
                    ::ViewProductionAiExplanations
                    ->value,

                PermissionName
                    ::GenerateDailyProductionReports
                    ->value,

                PermissionName
                    ::GenerateWeeklyProductionReports
                    ->value,

                PermissionName
                    ::ExportProductionReports
                    ->value,
            ],

            /*
             * Production managers receive complete read access and
             * reporting capability but do not execute operational
             * order, batch or validation actions.
             */
            RoleName::ProductionManager->value => [
                ...$personalAccountPermissions,

                PermissionName
                    ::ViewProductionManagerDashboard
                    ->value,

                PermissionName::ViewProductionTargets->value,

                PermissionName
                    ::ViewAllProductionOrders
                    ->value,

                PermissionName
                    ::ViewAllProductionRecords
                    ->value,

                PermissionName::ViewProductionEvents->value,

                PermissionName::CompareProductionLines->value,

                PermissionName::CompareProductionShifts->value,

                PermissionName::ViewProductionKpis->value,

                PermissionName
                    ::ViewProductionAnomalies
                    ->value,

                PermissionName
                    ::ViewProductionForecasts
                    ->value,

                PermissionName
                    ::ViewProductionAiExplanations
                    ->value,

                PermissionName
                    ::GenerateDailyProductionReports
                    ->value,

                PermissionName
                    ::GenerateWeeklyProductionReports
                    ->value,

                PermissionName
                    ::GenerateExecutiveProductionReports
                    ->value,

                PermissionName
                    ::ExportProductionReports
                    ->value,
            ],

            /*
             * Maintenance managers may view and resolve downtime and
             * machine incidents without changing production orders.
             */
            RoleName::MaintenanceManager->value => [
                ...$personalAccountPermissions,

                PermissionName
                    ::ViewMaintenanceManagerDashboard
                    ->value,

                PermissionName::ViewMachines->value,

                PermissionName::ViewDowntimeHistory->value,

                PermissionName::ReportDowntime->value,

                PermissionName
                    ::ReportMachineIncident
                    ->value,

                PermissionName::ViewProductionEvents->value,

                PermissionName
                    ::ResolveProductionEvents
                    ->value,

                PermissionName
                    ::CreateMaintenanceRequests
                    ->value,

                PermissionName
                    ::AssignMaintenanceRequests
                    ->value,

                PermissionName
                    ::UpdateMaintenanceRequests
                    ->value,

                PermissionName
                    ::CloseMaintenanceRequests
                    ->value,

                PermissionName
                    ::SchedulePreventiveMaintenance
                    ->value,

                PermissionName
                    ::RecordCorrectiveMaintenance
                    ->value,

                PermissionName
                    ::ViewMaintenanceHistory
                    ->value,

                PermissionName::ViewMaintenanceKpis->value,

                PermissionName
                    ::ViewMaintenanceAnomalies
                    ->value,

                PermissionName
                    ::ViewMaintenanceAiRecommendations
                    ->value,

                PermissionName
                    ::GenerateMaintenanceReports
                    ->value,
            ],

            RoleName::Administrator->value =>
                PermissionName::values(),
        ];
    }

    /**
     * Return every permission that may legally belong to a role.
     *
     * @return list<string>
     */
    public function allowedFor(
        RoleName $role
    ): array {
        return $this->baseline()[$role->value] ?? [];
    }

    /**
     * Return permissions that cannot be removed from a role.
     *
     * @return list<string>
     */
    public function mandatoryFor(
        RoleName $role
    ): array {
        $personalAccountPermissions = [
            PermissionName::ViewOwnProfile->value,
            PermissionName::UpdateOwnPassword->value,
        ];

        return match ($role) {
            RoleName::Operator => [
                ...$personalAccountPermissions,

                PermissionName::ViewOperatorDashboard->value,

                PermissionName
                    ::ViewAssignedProductionLine
                    ->value,

                PermissionName
                    ::ViewAssignedProductionOrders
                    ->value,

                PermissionName
                    ::CreateProductionRecords
                    ->value,

                PermissionName
                    ::ViewOwnProductionRecords
                    ->value,

                PermissionName
                    ::SubmitProductionRecords
                    ->value,
            ],

            RoleName::ProductionSupervisor => [
                ...$personalAccountPermissions,

                PermissionName
                    ::ViewProductionSupervisorDashboard
                    ->value,

                PermissionName
                    ::ViewAllProductionOrders
                    ->value,

                PermissionName
                    ::ViewAllProductionRecords
                    ->value,

                PermissionName
                    ::ValidateProductionRecords
                    ->value,

                PermissionName
                    ::RejectProductionRecords
                    ->value,

                PermissionName::ViewProductionKpis->value,
            ],

            RoleName::ProductionManager => [
                ...$personalAccountPermissions,

                PermissionName
                    ::ViewProductionManagerDashboard
                    ->value,

                PermissionName
                    ::ViewAllProductionOrders
                    ->value,

                PermissionName
                    ::ViewAllProductionRecords
                    ->value,

                PermissionName::ViewProductionKpis->value,
            ],

            RoleName::MaintenanceManager => [
                ...$personalAccountPermissions,

                PermissionName
                    ::ViewMaintenanceManagerDashboard
                    ->value,

                PermissionName::ViewMachines->value,

                PermissionName
                    ::ViewMaintenanceHistory
                    ->value,

                PermissionName::ViewMaintenanceKpis->value,

                PermissionName::ViewProductionEvents->value,
            ],

            RoleName::Administrator =>
                PermissionName::values(),
        };
    }

    /**
     * Prepare allowed permissions for the administration interface.
     *
     * @return array<
     *     string,
     *     list<array{
     *         value: string,
     *         label: string,
     *         mandatory: bool
     *     }>
     * >
     */
    public function groupedAllowedPermissions(
        RoleName $role
    ): array {
        $groups = [];

        $mandatoryPermissions =
            $this->mandatoryFor($role);

        foreach (
            $this->allowedFor($role)
            as $permission
        ) {
            $group = $this->groupLabel(
                $permission
            );

            $groups[$group][] = [
                'value' => $permission,

                'label' =>
                    $this->permissionLabel(
                        $permission
                    ),

                'mandatory' => in_array(
                    $permission,
                    $mandatoryPermissions,
                    true
                ),
            ];
        }

        return $groups;
    }

    public function permissionLabel(
        string $permission
    ): string {
        return Str::headline(
            str_replace(
                ['.', '-'],
                ' ',
                $permission
            )
        );
    }

    private function groupLabel(
        string $permission
    ): string {
        return match (true) {
            Str::startsWith(
                $permission,
                'account.'
            ) => 'Personal account',

            Str::startsWith(
                $permission,
                'dashboard.'
            ) => 'Dashboard access',

            Str::startsWith(
                $permission,
                'production.'
            ) => 'Production',

            Str::startsWith(
                $permission,
                'maintenance.'
            ) => 'Maintenance',

            Str::startsWith(
                $permission,
                [
                    'administration.users.',
                    'administration.roles.',
                    'administration.permissions.',
                ]
            ) => 'Users and access control',

            Str::startsWith(
                $permission,
                [
                    'administration.products.',
                    'administration.production-lines.',
                    'administration.machines.',
                    'administration.shifts.',
                ]
            ) => 'Master data',

            Str::startsWith(
                $permission,
                [
                    'administration.erp-settings.',
                    'administration.synchronization.',
                    'administration.synchronization-',
                ]
            ) => 'ERP integration',

            default => 'System administration',
        };
    }
}