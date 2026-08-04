<?php

namespace App\Repositories\Eloquent;

use App\Enums\RoleName;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

final class EloquentRoleRepository implements
    RoleRepositoryInterface
{
    /**
     * @return Collection<int, Role>
     */
    public function allSystemRoles(): Collection
    {
        $roleOrder = array_flip(
            RoleName::values()
        );

        return Role::query()
            ->with('permissions')
            ->where('guard_name', 'web')
            ->whereIn(
                'name',
                RoleName::values()
            )
            ->get()
            ->sortBy(
                static fn (Role $role): int =>
                    $roleOrder[$role->name]
                    ?? PHP_INT_MAX
            )
            ->values();
    }
}