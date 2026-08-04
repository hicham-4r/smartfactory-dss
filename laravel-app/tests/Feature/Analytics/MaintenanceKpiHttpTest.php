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

class MaintenanceKpiHttpTest extends TestCase
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
            route('analytics.maintenance.index')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_operator_cannot_view_maintenance_kpis(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::Operator
                )
            )
            ->get(
                route(
                    'analytics.maintenance.index'
                )
            )
            ->assertForbidden();
    }

    public function test_maintenance_manager_can_view_maintenance_kpis(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->get(
                route(
                    'analytics.maintenance.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'Maintenance KPI Summary'
            )
            ->assertSee(
                'No matching maintenance data'
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

    public function test_maintenance_manager_can_apply_valid_filters(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->get(
                route(
                    'analytics.maintenance.index',
                    [
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' =>
                            'Africa/Casablanca',
                        'maintenance_type' =>
                            'corrective',
                        'downtime_category' =>
                            'unplanned',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('2026-08-01')
            ->assertSee('2026-08-15')
            ->assertSee('Corrective')
            ->assertSee('Unplanned');
    }

    public function test_invalid_maintenance_filter_is_rejected(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->from(
                route(
                    'analytics.maintenance.index'
                )
            )
            ->get(
                route(
                    'analytics.maintenance.index',
                    [
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' =>
                            'UTC',
                        'maintenance_type' =>
                            'unsupported',
                    ]
                )
            )
            ->assertRedirect(
                route(
                    'analytics.maintenance.index'
                )
            )
            ->assertSessionHasErrors(
                'maintenance_type'
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
