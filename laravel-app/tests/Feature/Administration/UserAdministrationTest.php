<?php

namespace Tests\Feature\Administration;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_administrator_can_view_user_administration(): void
    {
        $administrator = $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get('/admin/users');

        $response
            ->assertOk()
            ->assertSeeText('User accounts')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_operator_cannot_view_user_administration(): void
    {
        $operator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $operator->assignRole(
            RoleName::Operator->value
        );

        $response = $this
            ->actingAs($operator)
            ->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_administrator_can_create_user_with_exactly_one_role(): void
    {
        $administrator = $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->post('/admin/users', [
                'name' => 'Production Operator',
                'email' => 'OPERATOR@SMARTFACTORY.TEST',
                'role' => RoleName::Operator->value,
            ]);

        $response
            ->assertRedirect('/admin/users')
            ->assertSessionHas(
                'temporary_password'
            );

        $user = User::query()
            ->where(
                'email',
                'operator@smartfactory.test'
            )
            ->firstOrFail();

        $temporaryPassword = (string) session(
            'temporary_password'
        );

        $this->assertTrue(
            $user->is_active
        );

        $this->assertTrue(
            $user->must_change_password
        );

        $this->assertSame(
            $administrator->getKey(),
            $user->created_by
        );

        $this->assertSame(
            $administrator->getKey(),
            $user->updated_by
        );

        $this->assertSame(
            [
                RoleName::Operator->value,
            ],
            $user
                ->getRoleNames()
                ->values()
                ->all()
        );

        $this->assertTrue(
            Hash::check(
                $temporaryPassword,
                $user->password
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $administrator->getKey(),

                'action' =>
                    AuditAction::UserCreated->value,

                'auditable_id' =>
                    (string) $user->getKey(),
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction::UserRolesChanged->value,

                'auditable_id' =>
                    (string) $user->getKey(),
            ]
        );
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $administrator = $this->administrator();

        User::factory()->create([
            'email' =>
                'duplicate@smartfactory.test',
        ]);

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'Duplicate User',
                'email' =>
                    'DUPLICATE@SMARTFACTORY.TEST',
                'role' =>
                    RoleName::Operator->value,
            ]);

        $response
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors('email');
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $administrator = $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->from('/admin/users')
            ->patch(
                '/admin/users/'
                .$administrator->getKey()
                .'/deactivate'
            );

        $response
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors('user');

        $administrator->refresh();

        $this->assertTrue(
            $administrator->is_active
        );
    }

    public function test_administrator_can_deactivate_and_activate_another_user(): void
    {
        $administrator = $this->administrator();

        $operator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $operator->assignRole(
            RoleName::Operator->value
        );

        $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->patch(
                '/admin/users/'
                .$operator->getKey()
                .'/deactivate'
            )
            ->assertRedirect('/admin/users');

        $operator->refresh();

        $this->assertFalse(
            $operator->is_active
        );

        $this->assertNotNull(
            $operator->deactivated_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction::UserDeactivated->value,

                'auditable_id' =>
                    (string) $operator->getKey(),
            ]
        );

        $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->patch(
                '/admin/users/'
                .$operator->getKey()
                .'/activate'
            )
            ->assertRedirect('/admin/users');

        $operator->refresh();

        $this->assertTrue(
            $operator->is_active
        );

        $this->assertNull(
            $operator->deactivated_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction::UserActivated->value,

                'auditable_id' =>
                    (string) $operator->getKey(),
            ]
        );
    }

    public function test_administrator_can_generate_new_temporary_password(): void
    {
        $administrator = $this->administrator();

        $operator = User::factory()->create([
            'password' => 'OldPassword!2026',
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $operator->assignRole(
            RoleName::Operator->value
        );

        $response = $this
            ->actingAs($administrator)
            ->withSession([
                'auth.password_confirmed_at' => time(),
            ])
            ->post(
                '/admin/users/'
                .$operator->getKey()
                .'/reset-password'
            );

        $response
            ->assertRedirect('/admin/users')
            ->assertSessionHas(
                'temporary_password'
            );

        $temporaryPassword = (string) session(
            'temporary_password'
        );

        $operator->refresh();

        $this->assertTrue(
            $operator->must_change_password
        );

        $this->assertNull(
            $operator->password_changed_at
        );

        $this->assertTrue(
            Hash::check(
                $temporaryPassword,
                $operator->password
            )
        );

        $this->assertFalse(
            Hash::check(
                'OldPassword!2026',
                $operator->password
            )
        );

        $auditLog = AuditLog::query()
            ->where(
                'action',
                AuditAction::UserPasswordReset->value
            )
            ->firstOrFail();

        $this->assertArrayNotHasKey(
            'password',
            $auditLog->new_values ?? []
        );
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $administrator->assignRole(
            RoleName::Administrator->value
        );

        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $administrator
            );
    }
}