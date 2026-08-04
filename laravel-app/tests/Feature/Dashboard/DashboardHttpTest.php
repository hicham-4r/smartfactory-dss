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

class DashboardHttpTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(
            route('dashboard')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_operator_sees_only_operator_workspace(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::Operator
                )
            )
            ->get(
                route('dashboard')
            )
            ->assertOk()
            ->assertSee(
                'Shared role-aware overview'
            )
            ->assertSee(
                'Operator workspace'
            )
            ->assertDontSee(
                'Production snapshot'
            )
            ->assertDontSee(
                'Maintenance snapshot'
            )
            ->assertDontSee(
                'Quality snapshot'
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

    public function test_production_manager_sees_production_and_quality_snapshots(): void
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
            ->assertSee(
                'Production snapshot'
            )
            ->assertSee(
                'Quality snapshot'
            )
            ->assertDontSee(
                'Maintenance snapshot'
            )
            ->assertSee(
                'Production performance'
            )
            ->assertSee(
                'Quality and lot release'
            );
    }

    public function test_maintenance_manager_sees_maintenance_snapshot(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->get(
                route('dashboard')
            )
            ->assertOk()
            ->assertSee(
                'Maintenance snapshot'
            )
            ->assertSee(
                'Maintenance performance'
            )
            ->assertDontSee(
                'Production snapshot'
            )
            ->assertDontSee(
                'Quality snapshot'
            );
    }

    public function test_shared_period_is_applied_and_preserved(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'dashboard',
                    [
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-10',
                        'timezone' =>
                            'UTC',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                '2026-08-01'
            )
            ->assertSee(
                '2026-08-10'
            )
            ->assertSee(
                'UTC'
            );
    }

    public function test_excessive_period_is_rejected(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
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
                            '2025-01-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' =>
                            'UTC',
                    ]
                )
            )
            ->assertRedirect(
                route('dashboard')
            )
            ->assertSessionHasErrors(
                'end_date'
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
