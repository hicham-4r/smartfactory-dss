<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Services\Authorization\RolePermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * Seed the complete fixed RBAC catalogue.
     */
    public function run(): void
    {
        $permissionRegistrar = app(
            PermissionRegistrar::class
        );

        $permissionRegistrar
            ->forgetCachedPermissions();

        foreach (
            PermissionName::cases()
            as $permission
        ) {
            Permission::findOrCreate(
                $permission->value,
                self::GUARD
            );
        }

        $matrix = app(
            RolePermissionMatrix::class
        );

        foreach (
            $matrix->baseline()
            as $role => $permissions
        ) {
            $roleModel = Role::findOrCreate(
                $role,
                self::GUARD
            );

            $roleModel->syncPermissions(
                $permissions
            );
        }

        /*
         * Ensure all five fixed roles exist even if the matrix is
         * refactored later.
         */
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate(
                $role->value,
                self::GUARD
            );
        }

        $permissionRegistrar
            ->forgetCachedPermissions();
    }
}