<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Authorization\RolePermissionAdministrationService;
use App\Services\Authorization\RolePermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class RoleManagementController extends Controller
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly RolePermissionMatrix $matrix,
        private readonly RolePermissionAdministrationService
            $rolePermissionService
    ) {
    }

    public function index(): Response
    {
        Gate::authorize(
            'viewAny',
            Role::class
        );

        return $this->noStoreView(
            'admin.roles.index',
            [
                'roles' =>
                    $this->roles
                        ->allSystemRoles(),

                'roleLabels' =>
                    $this->roleLabels(),
            ]
        );
    }

    public function edit(
        Role $role
    ): Response {
        Gate::authorize(
            'update',
            $role
        );

        $roleName = RoleName::tryFrom(
            $role->name
        );

        abort_if(
            $roleName === null,
            404
        );

        return $this->noStoreView(
            'admin.roles.edit',
            [
                'role' =>
                    $role->load('permissions'),

                'roleName' => $roleName,

                'permissionGroups' =>
                    $this->matrix
                        ->groupedAllowedPermissions(
                            $roleName
                        ),

                'selectedPermissions' =>
                    $role->permissions
                        ->pluck('name')
                        ->all(),
            ]
        );
    }

    public function update(
        UpdateRolePermissionsRequest $request,
        Role $role
    ): RedirectResponse {
        $this->rolePermissionService
            ->updatePermissions(
                role: $role,

                permissions:
                    $request->validated(
                        'permissions'
                    ),

                actor: $request->user()
            );

        return redirect()
            ->route('admin.roles.index')
            ->with(
                'status',
                'Role permissions were updated successfully.'
            );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function noStoreView(
        string $view,
        array $data
    ): Response {
        return response()
            ->view($view, $data)
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @return array<string, string>
     */
    private function roleLabels(): array
    {
        $labels = [];

        foreach (RoleName::cases() as $role) {
            $labels[$role->value] =
                $role->label();
        }

        return $labels;
    }
}