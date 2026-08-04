<?php

namespace App\Services\Authorization;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionAdministrationService
{
    public function __construct(
        private readonly RolePermissionMatrix $matrix,
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Synchronize a fixed operational role's permissions.
     *
     * @param list<string> $permissions
     */
    public function updatePermissions(
        Role $role,
        array $permissions,
        User $actor
    ): Role {
        $roleName = RoleName::tryFrom(
            $role->name
        );

        if ($roleName === null) {
            throw ValidationException::withMessages([
                'role' => [
                    'This is not a recognized system role.',
                ],
            ]);
        }

        if (
            $roleName === RoleName::Administrator
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'Administrator permissions are protected '
                    .'and cannot be modified.',
                ],
            ]);
        }

        $requestedPermissions = array_values(
            array_unique($permissions)
        );

        sort($requestedPermissions);

        $allowedPermissions =
            $this->matrix->allowedFor($roleName);

        $mandatoryPermissions =
            $this->matrix->mandatoryFor($roleName);

        $disallowedPermissions = array_diff(
            $requestedPermissions,
            $allowedPermissions
        );

        if ($disallowedPermissions !== []) {
            throw ValidationException::withMessages([
                'permissions' => [
                    'One or more permissions are not allowed '
                    .'for this role.',
                ],
            ]);
        }

        $missingMandatoryPermissions = array_diff(
            $mandatoryPermissions,
            $requestedPermissions
        );

        if (
            $missingMandatoryPermissions !== []
        ) {
            throw ValidationException::withMessages([
                'permissions' => [
                    'Mandatory permissions cannot be removed '
                    .'from this role.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $role,
                $actor,
                $requestedPermissions
            ): Role {
                $lockedRole = Role::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $role->getKey()
                    );

                $oldPermissions = $lockedRole
                    ->permissions()
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->all();

                $lockedRole->syncPermissions(
                    $requestedPermissions
                );

                app(PermissionRegistrar::class)
                    ->forgetCachedPermissions();

                $this->auditLogService->record(
                    action:
                        AuditAction
                            ::RolePermissionsChanged,

                    actor: $actor,
                    auditable: $lockedRole,

                    oldValues: [
                        'permissions' =>
                            $oldPermissions,
                    ],

                    newValues: [
                        'permissions' =>
                            $requestedPermissions,
                    ],

                    metadata: [
                        'role_name' =>
                            $lockedRole->name,
                    ]
                );

                return $lockedRole
                    ->load('permissions');
            },
            3
        );
    }
}