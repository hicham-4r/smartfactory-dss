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

class ProductionManagerDashboardHttpTest extends TestCase
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

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProductionWorkflowPermissionsSeeder::class);
        $this->seed(ProductionMasterDataSeeder::class);
    }

    public function test_production_manager_sees_dedicated_executive_dashboard(): void
    {
        $this
            ->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Production manager executive dashboard')
            ->assertSee('Production snapshot')
            ->assertSee('Monthly production trend')
            ->assertSee('Production-line comparison')
            ->assertSee('Quality snapshot')
            ->assertSee('Recent critical unresolved production events')
            ->assertDontSee('Pending production-record validations')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'private');
    }

    public function test_supervisor_does_not_see_manager_executive_dashboard(): void
    {
        $this
            ->actingAs($this->userWithRole(RoleName::ProductionSupervisor))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Production manager executive dashboard');
    }

    public function test_manager_filters_are_preserved(): void
    {
        $this
            ->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->get(
                route('dashboard', [
                    'start_date' => '2026-08-01',
                    'end_date' => '2026-08-10',
                    'timezone' => 'UTC',
                    'status' => 'completed',
                ])
            )
            ->assertOk()
            ->assertSee('2026-08-01')
            ->assertSee('2026-08-10')
            ->assertSee('completed');
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->assignRole($role->value);

        return $user;
    }
}
