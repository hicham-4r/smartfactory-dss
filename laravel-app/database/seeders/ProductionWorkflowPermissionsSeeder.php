<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class ProductionWorkflowPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * Add the production-workflow permissions without removing
     * permissions already assigned to the fixed application roles.
     */
    public function run(): void
    {
        $permissionRegistrar = app(
            PermissionRegistrar::class
        );

        $permissionRegistrar->forgetCachedPermissions();

        /**
         * Enum values must be used as array keys.
         *
         * @var array<string, list<PermissionName>> $assignments
         */
        $assignments = [
            RoleName::Operator->value => [
                PermissionName::SubmitProductionRecords,
                PermissionName::ViewProductionEvents,
            ],

            RoleName::ProductionSupervisor->value => [
                PermissionName::ViewAllProductionOrders,
                PermissionName::CreateProductionOrders,
                PermissionName::UpdateProductionOrders,
                PermissionName::ReleaseProductionOrders,
                PermissionName::CancelProductionOrders,
                PermissionName::CreateProductionBatches,
                PermissionName::ManageProductionBatches,
                PermissionName::SubmitProductionRecords,
                PermissionName::ViewProductionEvents,
                PermissionName::ResolveProductionEvents,
            ],

            RoleName::ProductionManager->value => [
                PermissionName::ViewAllProductionOrders,
                PermissionName::ViewProductionEvents,
            ],

            RoleName::MaintenanceManager->value => [
                PermissionName::ReportDowntime,
                PermissionName::ReportMachineIncident,
                PermissionName::ViewProductionEvents,
                PermissionName::ResolveProductionEvents,
            ],

            RoleName::Administrator->value => [
                PermissionName::ViewAllProductionOrders,
                PermissionName::CreateProductionOrders,
                PermissionName::UpdateProductionOrders,
                PermissionName::ReleaseProductionOrders,
                PermissionName::CancelProductionOrders,
                PermissionName::CreateProductionBatches,
                PermissionName::ManageProductionBatches,
                PermissionName::SubmitProductionRecords,
                PermissionName::ViewProductionEvents,
                PermissionName::ResolveProductionEvents,
            ],
        ];

        try {
            DB::transaction(
                function () use ($assignments): void {
                    foreach (
                        $assignments as
                        $roleName => $permissionNames
                    ) {
                        $role = Role::findOrCreate(
                            $roleName,
                            self::GUARD
                        );

                        foreach (
                            $permissionNames as $permissionName
                        ) {
                            $permission =
                                Permission::findOrCreate(
                                    $permissionName->value,
                                    self::GUARD
                                );

                            /*
                             * This operation is idempotent. Spatie
                             * does not create a duplicate pivot row
                             * when the permission is already assigned.
                             */
                            $role->givePermissionTo(
                                $permission
                            );
                        }
                    }
                },
                attempts: 3
            );
        } catch (Throwable $exception) {
            $permissionRegistrar
                ->forgetCachedPermissions();

            throw $exception;
        }

        $permissionRegistrar->forgetCachedPermissions();
    }
}