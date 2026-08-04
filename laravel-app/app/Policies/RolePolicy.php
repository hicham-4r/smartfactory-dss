<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Spatie\Permission\Models\Role;

final class RolePolicy
{
    /**
     * Determine whether the role catalogue may be viewed.
     */
    public function viewAny(
        User $user
    ): bool {
        return $user->can(
            PermissionName::ViewRoles->value
        ) && $user->can(
            PermissionName::ViewPermissions->value
        );
    }

    /**
     * Determine whether one system role may be viewed.
     */
    public function view(
        User $user,
        Role $role
    ): bool {
        return $this->isSystemRole($role)
            && $this->viewAny($user);
    }

    /**
     * Determine whether a role's permissions may be changed.
     */
    public function update(
        User $user,
        Role $role
    ): bool {
        $roleName = RoleName::tryFrom(
            $role->name
        );

        if (
            $roleName === null
            || $roleName === RoleName::Administrator
        ) {
            return false;
        }

        return $user->can(
            PermissionName::ManageRoles->value
        ) && $user->can(
            PermissionName::ManagePermissions->value
        );
    }

    private function isSystemRole(
        Role $role
    ): bool {
        return RoleName::tryFrom(
            $role->name
        ) !== null;
    }
}