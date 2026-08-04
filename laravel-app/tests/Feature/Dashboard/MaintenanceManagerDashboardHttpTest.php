<?php

namespace Tests\Feature\Dashboard;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceManagerDashboardHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-08-15 10:00:00',
                'Africa/Casablanca'
            )
        );

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->seed(
            ProductionWorkflowPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_maintenance_manager_sees_dedicated_dashboard(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(
                'Maintenance manager operational dashboard'
            )
            ->assertSee(
                'Maintenance snapshot'
            )
            ->assertSee(
                'Machine status and maintenance indicators'
            )
            ->assertSee(
                'Maintenance intervention status'
            )
            ->assertDontSee(
                'Production manager executive dashboard'
            )
            ->assertDontSee(
                'Production supervisor operational dashboard'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            );
    }

    public function test_other_roles_do_not_see_maintenance_manager_dashboard(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(
                'Maintenance manager operational dashboard'
            );
    }

    public function test_maintenance_filters_are_preserved(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->get(
                route('dashboard', [
                    'start_date' =>
                        '2026-08-01',
                    'end_date' =>
                        '2026-08-10',
                    'timezone' => 'UTC',
                    'maintenance_type' =>
                        'corrective',
                    'downtime_category' =>
                        'unplanned',
                ])
            )
            ->assertOk()
            ->assertSee('2026-08-01')
            ->assertSee('2026-08-10')
            ->assertSee('corrective')
            ->assertSee('unplanned');
    }

    public function test_machine_must_belong_to_selected_line(): void
    {
        $machines = Machine::query()
            ->orderBy('id')
            ->get();

        $first = $machines
            ->firstOrFail();

        $other = $machines->first(
            fn (Machine $machine): bool =>
                $machine->production_line_id
                !== $first->production_line_id
        );

        $this->assertNotNull($other);

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->from(route('dashboard'))
            ->get(
                route('dashboard', [
                    'start_date' =>
                        '2026-08-01',
                    'end_date' =>
                        '2026-08-10',
                    'timezone' => 'UTC',
                    'production_line_id' =>
                        $first->production_line_id,
                    'machine_id' =>
                        $other->getKey(),
                ])
            )
            ->assertRedirect(
                route('dashboard')
            )
            ->assertSessionHasErrors(
                'machine_id'
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
