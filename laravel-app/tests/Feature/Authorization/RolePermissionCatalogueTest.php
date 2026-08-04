<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_all_required_roles_are_created(): void
    {
        $this->assertCount(
            count(RoleName::cases()),
            Role::query()->get()
        );

        foreach (RoleName::cases() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_complete_permission_catalogue_is_created(): void
    {
        $this->assertCount(
            count(PermissionName::cases()),
            Permission::query()->get()
        );

        foreach (PermissionName::cases() as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_operator_receives_only_operator_permissions(): void
    {
        $user = User::factory()->create();

        $user->assignRole(
            RoleName::Operator->value
        );

        $this->assertTrue(
            $user->can(
                PermissionName::CreateProductionRecords->value
            )
        );

        $this->assertTrue(
            $user->can(
                PermissionName::ReportDowntime->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ValidateProductionRecords->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ViewProductionKpis->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ViewUsers->value
            )
        );
    }

    public function test_production_supervisor_cannot_manage_users(): void
    {
        $user = User::factory()->create();

        $user->assignRole(
            RoleName::ProductionSupervisor->value
        );

        $this->assertTrue(
            $user->can(
                PermissionName::ValidateProductionRecords->value
            )
        );

        $this->assertTrue(
            $user->can(
                PermissionName::ViewProductionAnomalies->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ManageRoles->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ManageErpConnectorSettings->value
            )
        );
    }

    public function test_production_manager_receives_executive_permissions(): void
    {
        $user = User::factory()->create();

        $user->assignRole(
            RoleName::ProductionManager->value
        );

        $this->assertTrue(
            $user->can(
                PermissionName::ViewProductionKpis->value
            )
        );

        $this->assertTrue(
            $user->can(
                PermissionName::GenerateExecutiveProductionReports->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::UpdateUsers->value
            )
        );
    }

    public function test_maintenance_manager_receives_maintenance_permissions_only(): void
    {
        $user = User::factory()->create();

        $user->assignRole(
            RoleName::MaintenanceManager->value
        );

        $this->assertTrue(
            $user->can(
                PermissionName::SchedulePreventiveMaintenance->value
            )
        );

        $this->assertTrue(
            $user->can(
                PermissionName::CloseMaintenanceRequests->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ValidateProductionRecords->value
            )
        );

        $this->assertFalse(
            $user->can(
                PermissionName::ManageSystemSettings->value
            )
        );
    }

    public function test_administrator_receives_every_registered_permission(): void
    {
        $administrator = User::factory()->create();

        $administrator->assignRole(
            RoleName::Administrator->value
        );

        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue(
                $administrator->can($permission->value),
                sprintf(
                    'Administrator is missing permission [%s].',
                    $permission->value
                )
            );
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $expectedRoleCount = count(RoleName::cases());

        $expectedPermissionCount =
            count(PermissionName::cases());

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->assertSame(
            $expectedRoleCount,
            Role::query()->count()
        );

        $this->assertSame(
            $expectedPermissionCount,
            Permission::query()->count()
        );
    }
}