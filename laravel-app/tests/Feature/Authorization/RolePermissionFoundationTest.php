<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function test_user_can_receive_a_role(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'operator',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        $this->assertTrue(
            $user->hasRole('operator')
        );

        $this->assertFalse(
            $user->hasRole('administrator')
        );
    }

    public function test_role_can_receive_a_permission(): void
    {
        $permission = Permission::create([
            'name' => 'view-assigned-production-line',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'operator',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $this->assertTrue(
            $role->hasPermissionTo(
                'view-assigned-production-line'
            )
        );
    }

    public function test_user_inherits_permissions_from_role(): void
    {
        $permission = Permission::create([
            'name' => 'report-downtime',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'operator',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();

        $user->assignRole($role);

        $this->assertTrue(
            $user->can('report-downtime')
        );

        $this->assertFalse(
            $user->can('manage-users')
        );
    }

    public function test_roles_and_permissions_are_not_mass_assignable(): void
    {
        $user = new User();

        $user->fill([
            'name' => 'Mass Assignment Test',
            'email' => 'mass-assignment@smartfactory.test',
            'password' => 'SmartFactory!2026',
            'role' => 'administrator',
            'roles' => ['administrator'],
            'permissions' => ['manage-users'],
        ]);

        $this->assertSame(
            ['name', 'email', 'password'],
            $user->getFillable()
        );

        $this->assertNotContains(
            'role',
            $user->getFillable()
        );

        $this->assertNotContains(
            'roles',
            $user->getFillable()
        );

        $this->assertNotContains(
            'permissions',
            $user->getFillable()
        );

        /*
         * A singular "role" attribute does not exist.
         */
        $this->assertNull(
            $user->getAttribute('role')
        );

        /*
         * Spatie defines "roles" and "permissions" as relationships.
         * They correctly return empty collections rather than null.
         */
        $this->assertTrue(
            $user->roles->isEmpty()
        );

        $this->assertTrue(
            $user->permissions->isEmpty()
        );
    }

    public function test_web_and_api_guards_are_not_mixed(): void
    {
        Role::create([
            'name' => 'operator',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'operator',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'operator',
            'guard_name' => 'api',
        ]);
    }
}