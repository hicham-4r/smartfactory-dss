<?php

namespace Tests\Feature\Analytics;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnalyticsDrilldownHttpTest extends TestCase
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

    public function test_guest_is_redirected_from_production_drilldown(): void
    {
        $line = ProductionLine::query()
            ->firstOrFail();

        $this
            ->get(
                route(
                    'analytics.production.lines.show',
                    $line
                )
            )
            ->assertRedirect(
                route('login')
            );
    }

    public function test_operator_cannot_access_company_wide_drilldowns(): void
    {
        $line = ProductionLine::query()
            ->firstOrFail();

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::Operator
                )
            )
            ->get(
                route(
                    'analytics.production.lines.show',
                    $line
                )
            )
            ->assertForbidden();
    }

    public function test_production_manager_can_open_line_drilldown(): void
    {
        $line = ProductionLine::query()
            ->firstOrFail();

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'analytics.production.lines.show',
                    [
                        'productionLine' =>
                            $line->getKey(),
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' => 'UTC',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Production line performance detail'
            )
            ->assertSee(
                $line->name
            )
            ->assertSee(
                'No production data'
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

    public function test_maintenance_manager_can_open_machine_drilldown(): void
    {
        $machine = Machine::query()
            ->firstOrFail();

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::MaintenanceManager
                )
            )
            ->get(
                route(
                    'analytics.maintenance.machines.show',
                    [
                        'machine' =>
                            $machine->getKey(),
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' => 'UTC',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Machine maintenance detail'
            )
            ->assertSee(
                $machine->name
            )
            ->assertSee(
                'No maintenance data'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            );
    }

    public function test_production_manager_can_open_quality_product_drilldown(): void
    {
        $product = Product::query()
            ->firstOrFail();

        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'analytics.quality.products.show',
                    [
                        'product' =>
                            $product->getKey(),
                        'start_date' =>
                            '2026-08-01',
                        'end_date' =>
                            '2026-08-15',
                        'timezone' => 'UTC',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Product quality detail'
            )
            ->assertSee(
                $product->name
            )
            ->assertSee(
                'No quality data'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            );
    }

    public function test_missing_drilldown_entity_returns_safe_not_found_page(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::ProductionManager
                )
            )
            ->get(
                route(
                    'analytics.production.lines.show',
                    [
                        'productionLine' => 999999,
                    ]
                )
            )
            ->assertNotFound();
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
