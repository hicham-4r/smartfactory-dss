<?php

namespace Tests\Feature\Analytics;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionKpiHttpTest extends TestCase
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
            route('analytics.production.index')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_operator_cannot_view_production_kpis(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::Operator
                )
            )
            ->get(
                route('analytics.production.index')
            )
            ->assertForbidden();
    }

    public function test_production_manager_can_view_production_kpis(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route('analytics.production.index')
            )
            ->assertOk()
            ->assertSee('Production KPI Summary')
            ->assertSee('No matching production data')
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            );
    }

    public function test_supervisor_can_apply_valid_filters(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionSupervisor
                )
            )
            ->get(
                route(
                    'analytics.production.index',
                    [
                        'start_date' => '2026-08-01',
                        'end_date' => '2026-08-15',
                        'timezone' =>
                            'Africa/Casablanca',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('2026-08-01')
            ->assertSee('2026-08-15')
            ->assertSee('Africa/Casablanca');
    }

    public function test_date_range_longer_than_configured_limit_is_rejected(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->from(
                route('analytics.production.index')
            )
            ->get(
                route(
                    'analytics.production.index',
                    [
                        'start_date' => '2025-01-01',
                        'end_date' => '2026-08-15',
                        'timezone' => 'UTC',
                    ]
                )
            )
            ->assertRedirect(
                route('analytics.production.index')
            )
            ->assertSessionHasErrors('end_date');
    }

    private function userWithRole(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->assignRole($role->value);

        return $user;
    }
}
