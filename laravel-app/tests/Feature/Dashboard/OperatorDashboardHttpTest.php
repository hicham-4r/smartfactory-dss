<?php

namespace Tests\Feature\Dashboard;

use App\Enums\RoleName;
use App\Models\OperatorAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorDashboardHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-07-15 10:00:00',
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

    public function test_unlinked_operator_sees_safe_linkage_warning(): void
    {
        $this
            ->actingAs(
                $this->userWithRole(
                    RoleName::Operator
                )
            )
            ->get(
                route(
                    'dashboard',
                    [
                        'start_date' => '2026-07-01',
                        'end_date' => '2026-07-31',
                        'timezone' => 'Africa/Casablanca',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Operator limited dashboard'
            )
            ->assertSee(
                'Operator profile not linked'
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

    public function test_linked_operator_sees_only_personal_operational_dashboard(): void
    {
        $assignment =
            OperatorAssignment::query()
                ->with('operator')
                ->orderBy('id')
                ->firstOrFail();

        $user = $this->userWithRole(
            RoleName::Operator
        );

        $assignment->operator
            ->forceFill([
                'user_id' => $user->getKey(),
            ])
            ->save();

        $this
            ->actingAs($user)
            ->get(
                route(
                    'dashboard',
                    [
                        'start_date' => '2026-07-01',
                        'end_date' => '2026-07-31',
                        'timezone' => 'Africa/Casablanca',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Operator limited dashboard'
            )
            ->assertSee(
                $assignment->operator->full_name
            )
            ->assertSee(
                'Operator identity and current assignments'
            )
            ->assertSee(
                'Personal production quantities'
            )
            ->assertSee(
                'Assigned released and in-progress work'
            )
            ->assertDontSee(
                'Production manager executive dashboard'
            )
            ->assertDontSee(
                'Maintenance manager operational dashboard'
            );
    }

    public function test_production_manager_does_not_receive_operator_dashboard(): void
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
            ->assertDontSee(
                'Operator limited dashboard'
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
