<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * Return all fixed system roles with their permissions.
     *
     * @return Collection<int, Role>
     */
    public function allSystemRoles(): Collection;
}