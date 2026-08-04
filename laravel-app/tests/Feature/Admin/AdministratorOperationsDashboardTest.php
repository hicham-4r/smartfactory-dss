<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\Operator;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdministratorOperationsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this
            ->get(
                route('admin.dashboard')
            )
            ->assertRedirect(
                route('login')
            );
    }

    public function test_operator_cannot_open_administrator_operations(): void
    {
        $operator = $this->userWithRole(
            RoleName::Operator
        );

        $this
            ->actingAs($operator)
            ->get(
                route('admin.dashboard')
            )
            ->assertForbidden();
    }

    public function test_administrator_can_open_sanitized_operations_dashboard(): void
    {
        $admin = $this->administrator();

        $operatorAccount = $this->userWithRole(
            RoleName::Operator
        );

        $operator = new Operator();

        $operator->forceFill([
            'employee_code' => 'OP-OPS-001',
            'first_name' => 'Operations',
            'last_name' => 'Operator',
            'is_active' => true,
            'source_system' => 'simulated_sage',
            'external_id' => 'ERP-OP-OPS-001',
        ])->save();

        DB::table('audit_logs')->insert([
            'event_id' => (string) Str::uuid(),
            'actor_id' => $admin->getKey(),
            'action' => 'administration.test-action',
            'auditable_type' => User::class,
            'auditable_id' =>
                (string) $operatorAccount->getKey(),
            'old_values' => json_encode([
                'secret' => 'OLD-SECRET-VALUE',
            ]),
            'new_values' => json_encode([
                'secret' => 'NEW-SECRET-VALUE',
            ]),
            'metadata' => json_encode([
                'authorization' =>
                    'Bearer TOP-SECRET-TOKEN',
            ]),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->get(
                route('admin.dashboard')
            )
            ->assertOk()
            ->assertSee(
                'Administrator operations dashboard'
            )
            ->assertSee(
                'Application and queue health'
            )
            ->assertSee(
                'ERP synchronization health'
            )
            ->assertSee(
                'Recent sanitized audit activity'
            )
            ->assertSee(
                'administration.test-action'
            )
            ->assertDontSee(
                'TOP-SECRET-TOKEN'
            )
            ->assertDontSee(
                'OLD-SECRET-VALUE'
            )
            ->assertDontSee(
                'NEW-SECRET-VALUE'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'max-age=0'
            );
    }

    private function administrator(): User
    {
        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $this->userWithRole(
                    RoleName::Administrator
                )
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
