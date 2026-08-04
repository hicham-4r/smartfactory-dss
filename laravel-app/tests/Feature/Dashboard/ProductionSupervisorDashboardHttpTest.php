<?php

namespace Tests\Feature\Dashboard;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSupervisorDashboardHttpTest extends TestCase
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

    public function test_production_supervisor_sees_dedicated_operational_dashboard(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionSupervisor
                )
            )
            ->get(
                route(
                    'dashboard',
                    [
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' =>
                            'UTC',
                        'status' =>
                            'in_progress',
                    ]
                )
            )
            ->assertOk()
            ->assertSeeText(
                'Production Supervisor operational dashboard'
            )
            ->assertSeeText(
                'Pending validations'
            )
            ->assertSeeText(
                'Unresolved production events'
            )
            ->assertSeeText(
                'All execution statuses'
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

    public function test_other_roles_do_not_receive_supervisor_operational_section(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route('dashboard')
            )
            ->assertOk()
            ->assertDontSeeText(
                'Production Supervisor operational dashboard'
            );
    }

    public function test_dashboard_rejects_non_execution_status(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionSupervisor
                )
            )
            ->from(
                route('dashboard')
            )
            ->get(
                route(
                    'dashboard',
                    [
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' =>
                            'UTC',
                        'status' => 'draft',
                    ]
                )
            )
            ->assertRedirect(
                route('dashboard')
            )
            ->assertSessionHasErrors(
                'status'
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
