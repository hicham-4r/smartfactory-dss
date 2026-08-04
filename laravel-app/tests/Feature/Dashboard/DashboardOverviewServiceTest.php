<?php

namespace Tests\Feature\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Dashboard\DashboardOverviewService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_operator_receives_workspace_without_cross_domain_snapshots(): void
    {
        $overview = app(
            DashboardOverviewService::class
        )->build(
            $this->userWithRole(
                RoleName::Operator
            ),
            $this->filter()
        );

        $this->assertSame(
            RoleName::Operator,
            $overview->primaryRole
        );

        $this->assertSame(
            [],
            array_map(
                static fn (
                    $card
                ): string => $card->key,
                $overview->modules
            )
        );

        $this->assertNotNull(
            $overview->operatorDashboard
        );

        $this->assertFalse(
            $overview
                ->operatorDashboard
                ->profileLinked
        );

        $this->assertNull(
            $overview->production
        );

        $this->assertNull(
            $overview->maintenance
        );

        $this->assertNull(
            $overview->quality
        );
    }

    public function test_production_manager_receives_production_and_quality_snapshots(): void
    {
        $overview = app(
            DashboardOverviewService::class
        )->build(
            $this->userWithRole(
                RoleName::ProductionManager
            ),
            $this->filter()
        );

        $this->assertNotNull(
            $overview->production
        );

        $this->assertNotNull(
            $overview->quality
        );

        $this->assertNull(
            $overview->maintenance
        );

        $this->assertSame(
            [
                'production-analytics',
                'quality-analytics',
            ],
            array_map(
                static fn (
                    $card
                ): string => $card->key,
                $overview->modules
            )
        );
    }

    public function test_maintenance_manager_receives_only_maintenance_snapshot(): void
    {
        $overview = app(
            DashboardOverviewService::class
        )->build(
            $this->userWithRole(
                RoleName::MaintenanceManager
            ),
            $this->filter()
        );

        $this->assertNull(
            $overview->production
        );

        $this->assertNotNull(
            $overview->maintenance
        );

        $this->assertNull(
            $overview->quality
        );

        $this->assertSame(
            ['maintenance-analytics'],
            array_map(
                static fn (
                    $card
                ): string => $card->key,
                $overview->modules
            )
        );
    }

    private function filter(): DashboardFilter
    {
        return new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-08-01',
                'Africa/Casablanca'
            ),
            endDate: CarbonImmutable::parse(
                '2026-08-15',
                'Africa/Casablanca'
            ),
            timezone: 'Africa/Casablanca',
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
