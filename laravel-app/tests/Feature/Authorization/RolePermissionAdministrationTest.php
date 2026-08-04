<?php

namespace Tests\Feature\Authorization;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Authorization\RolePermissionMatrix;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_administrator_can_view_role_catalogue(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get('/admin/roles');

        $response
            ->assertOk()
            ->assertSeeText(
                'Roles and permissions'
            )
            ->assertSeeText(
                'Administrator'
            )
            ->assertHeader(
                'Pragma',
                'no-cache'
            );
    }

    public function test_operator_cannot_view_role_catalogue(): void
    {
        $operator = $this->userWithRole(
            RoleName::Operator
        );

        $response = $this
            ->actingAs($operator)
            ->get('/admin/roles');

        $response->assertForbidden();
    }

    public function test_administrator_role_is_immutable(): void
    {
        $administrator =
            $this->administrator();

        $administratorRole =
            Role::findByName(
                RoleName::Administrator->value,
                'web'
            );

        $this->assertFalse(
            Gate::forUser($administrator)
                ->allows(
                    'update',
                    $administratorRole
                )
        );

        $response = $this
            ->actingAs($administrator)
            ->get(
                '/admin/roles/'
                .$administratorRole->getKey()
                .'/edit'
            );

        $response->assertForbidden();
    }

    public function test_cross_domain_permission_cannot_be_assigned_to_operator(): void
    {
        $administrator =
            $this->administrator();

        $operatorRole = Role::findByName(
            RoleName::Operator->value,
            'web'
        );

        $permissions = app(
            RolePermissionMatrix::class
        )->mandatoryFor(
            RoleName::Operator
        );

        $permissions[] =
            PermissionName::ViewUsers->value;

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->from(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/edit'
            )
            ->put(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/permissions',
                [
                    'permissions' =>
                        $permissions,
                ]
            );

        $response
            ->assertRedirect(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/edit'
            )
            ->assertSessionHasErrors();

        $operatorRole->refresh();

        $this->assertFalse(
            $operatorRole->hasPermissionTo(
                PermissionName::ViewUsers->value
            )
        );
    }

    public function test_mandatory_permission_cannot_be_removed(): void
    {
        $administrator =
            $this->administrator();

        $operatorRole = Role::findByName(
            RoleName::Operator->value,
            'web'
        );

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->from(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/edit'
            )
            ->put(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/permissions',
                [
                    'permissions' => [
                        PermissionName
                            ::ReportDowntime
                            ->value,
                    ],
                ]
            );

        $response
            ->assertRedirect(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/edit'
            )
            ->assertSessionHasErrors(
                'permissions'
            );

        $operatorRole->refresh();

        $this->assertTrue(
            $operatorRole->hasPermissionTo(
                PermissionName
                    ::ViewOperatorDashboard
                    ->value
            )
        );
    }

    public function test_administrator_can_remove_optional_permission_from_operational_role(): void
    {
        $administrator =
            $this->administrator();

        $operatorRole = Role::findByName(
            RoleName::Operator->value,
            'web'
        );

        $permissions = array_values(
            array_filter(
                app(
                    RolePermissionMatrix::class
                )->allowedFor(
                    RoleName::Operator
                ),

                static fn (
                    string $permission
                ): bool =>
                    $permission
                    !== PermissionName
                        ::AddProductionEventComment
                        ->value
            )
        );

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->put(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/permissions',
                [
                    'permissions' =>
                        $permissions,
                ]
            );

        $response
            ->assertRedirect('/admin/roles')
            ->assertSessionHas(
                'status',
                'Role permissions were updated successfully.'
            );

        $operatorRole->refresh();

        $this->assertFalse(
            $operatorRole->hasPermissionTo(
                PermissionName
                    ::AddProductionEventComment
                    ->value
            )
        );

        $this->assertTrue(
            $operatorRole->hasPermissionTo(
                PermissionName
                    ::ViewOperatorDashboard
                    ->value
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $administrator->getKey(),

                'action' =>
                    AuditAction
                        ::RolePermissionsChanged
                        ->value,

                'auditable_id' =>
                    (string) $operatorRole->getKey(),
            ]
        );
    }

    public function test_non_administrator_cannot_change_role_permissions(): void
    {
        $operator =
            $this->userWithRole(
                RoleName::Operator
            );

        $operatorRole = Role::findByName(
            RoleName::Operator->value,
            'web'
        );

        $response = $this
            ->actingAs($operator)
            ->withSession([
                'auth.password_confirmed_at' =>
                    time(),
            ])
            ->put(
                '/admin/roles/'
                .$operatorRole->getKey()
                .'/permissions',
                [
                    'permissions' => app(
                        RolePermissionMatrix::class
                    )->allowedFor(
                        RoleName::Operator
                    ),
                ]
            );

        $response->assertForbidden();
    }

    private function administrator(): User
    {
        $administrator = $this->userWithRole(
            RoleName::Administrator
        );

        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $administrator
            );
    }

    private function userWithRole(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }
}