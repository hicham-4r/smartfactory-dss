<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Services\Authorization\RolePermissionMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

final class UpdateRolePermissionsRequest extends
    FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && (
                $this->user()?->can(
                    'update',
                    $role
                ) ?? false
            );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $roleName = $this->roleName();

        $allowedPermissions = $roleName
            ? app(RolePermissionMatrix::class)
                ->allowedFor($roleName)
            : [];

        return [
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],

            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($allowedPermissions),
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $roleName = $this->roleName();

                if ($roleName === null) {
                    $validator
                        ->errors()
                        ->add(
                            'role',
                            'The selected role is invalid.'
                        );

                    return;
                }

                $permissions = array_values(
                    array_unique(
                        (array) $this->input(
                            'permissions',
                            []
                        )
                    )
                );

                $mandatoryPermissions = app(
                    RolePermissionMatrix::class
                )->mandatoryFor($roleName);

                if (
                    array_diff(
                        $mandatoryPermissions,
                        $permissions
                    ) !== []
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'permissions',
                            'Mandatory permissions '
                            .'cannot be removed.'
                        );
                }
            }
        );
    }

    private function roleName(): ?RoleName
    {
        $role = $this->route('role');

        if (! $role instanceof Role) {
            return null;
        }

        return RoleName::tryFrom(
            $role->name
        );
    }
}