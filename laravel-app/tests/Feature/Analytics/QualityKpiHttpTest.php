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

class QualityKpiHttpTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('analytics.quality.index'))
            ->assertRedirect(route('login'));
    }

    public function test_operator_cannot_view_quality_kpis(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator))
            ->get(route('analytics.quality.index'))
            ->assertForbidden();
    }

    public function test_production_manager_can_view_quality_kpis(): void
    {
        $this->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->get(route('analytics.quality.index'))
            ->assertOk()
            ->assertSee('Quality KPI Summary')
            ->assertSee(
                'No matching quality data exists for the selected filters.'
            )
            ->assertDontSee('Sample failure rate')
            ->assertDontSee('failed of 0 sampled')
            ->assertSee(
                'All displayed records are simulated ERP or DSS prototype data.'
            )
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'private');
    }

    public function test_supervisor_can_apply_valid_filters(): void
    {
        $this->actingAs($this->userWithRole(RoleName::ProductionSupervisor))
            ->get(
                route('analytics.quality.index', [
                    'start_date' => '2026-08-01',
                    'end_date' => '2026-08-15',
                    'timezone' => 'Africa/Casablanca',
                    'inspection_result' => 'failed',
                    'lot_status' => 'blocked',
                    'nonconformity_severity' => 'critical',
                ])
            )
            ->assertOk()
            ->assertSee('2026-08-01')
            ->assertSee('2026-08-15')
            ->assertSee('Failed')
            ->assertSee('Blocked')
            ->assertSee('Critical');
    }

    public function test_invalid_quality_filter_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(RoleName::ProductionManager))
            ->from(route('analytics.quality.index'))
            ->get(
                route('analytics.quality.index', [
                    'start_date' => '2026-08-01',
                    'end_date' => '2026-08-15',
                    'timezone' => 'UTC',
                    'inspection_result' => 'unsupported',
                ])
            )
            ->assertRedirect(route('analytics.quality.index'))
            ->assertSessionHasErrors('inspection_result');
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
