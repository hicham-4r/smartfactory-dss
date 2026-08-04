<?php

namespace Tests\Feature\Security;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MandatoryPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_guest_cannot_view_required_password_change_page(): void
    {
        $response = $this->get(
            '/security/password/change-required'
        );

        $response->assertRedirect('/login');
    }

    public function test_user_with_temporary_password_can_view_change_page(): void
    {
        $user = User::factory()->create([
            'password' => 'TemporaryPassword!2026',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/security/password/change-required');

        $response
            ->assertOk()
            ->assertSeeText(
                'Replace your temporary password'
            );

        $cacheControl = (string)
            $response->headers->get(
                'Cache-Control'
            );

        $this->assertStringContainsString(
            'no-store',
            $cacheControl
        );

        $this->assertStringContainsString(
            'no-cache',
            $cacheControl
        );

        $this->assertStringContainsString(
            'must-revalidate',
            $cacheControl
        );

        $this->assertStringContainsString(
            'private',
            $cacheControl
        );

        $this->assertStringContainsString(
            'max-age=0',
            $cacheControl
        );

        $response->assertHeader(
            'Pragma',
            'no-cache'
        );

        $response->assertHeader(
            'Expires',
            '0'
        );
    }

    public function test_user_with_temporary_password_is_blocked_from_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(
            '/security/password/change-required'
        );
    }

    public function test_administrator_with_temporary_password_is_blocked_from_admin_dashboard(): void
    {
        $administrator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $administrator->assignRole(
            RoleName::Administrator->value
        );

        $response = $this
            ->actingAs($administrator)
            ->get('/admin');

        $response->assertRedirect(
            '/security/password/change-required'
        );
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'TemporaryPassword!2026',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/security/password/change-required')
            ->put(
                '/security/password/change-required',
                [
                    'current_password' =>
                        'IncorrectPassword!2026',

                    'password' =>
                        'NewPrivatePassword!2026',

                    'password_confirmation' =>
                        'NewPrivatePassword!2026',
                ]
            );

        $response
            ->assertRedirect(
                '/security/password/change-required'
            )
            ->assertSessionHasErrors(
                'current_password'
            );

        $user->refresh();

        $this->assertTrue(
            $user->must_change_password
        );

        $this->assertTrue(
            Hash::check(
                'TemporaryPassword!2026',
                $user->password
            )
        );
    }

    public function test_temporary_password_can_be_replaced_securely(): void
    {
        $user = User::factory()->create([
            'password' => 'TemporaryPassword!2026',
            'is_active' => true,
            'must_change_password' => true,
            'remember_token' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->put(
                '/security/password/change-required',
                [
                    'current_password' =>
                        'TemporaryPassword!2026',

                    'password' =>
                        'NewPrivatePassword!2026',

                    'password_confirmation' =>
                        'NewPrivatePassword!2026',
                ]
            );

        $response
            ->assertRedirect('/dashboard')
            ->assertSessionHas(
                'status',
                'Your password was changed successfully.'
            );

        $user->refresh();

        $this->assertFalse(
            $user->must_change_password
        );

        $this->assertNotNull(
            $user->password_changed_at
        );

        $this->assertNotNull(
            $user->remember_token
        );

        $this->assertSame(
            $user->getKey(),
            $user->updated_by
        );

        $this->assertTrue(
            Hash::check(
                'NewPrivatePassword!2026',
                $user->password
            )
        );

        $this->assertFalse(
            Hash::check(
                'TemporaryPassword!2026',
                $user->password
            )
        );
    }

    public function test_administrator_can_access_admin_dashboard_after_password_change(): void
    {
        $administrator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $administrator->assignRole(
            RoleName::Administrator->value
        );

        $administrator = $this
            ->enableConfirmedTwoFactorAuthentication(
                $administrator
            );

        $response = $this
            ->actingAs($administrator)
            ->get('/admin');

        $response
            ->assertOk()
            ->assertSeeText(
                'Administrator operations'
            );
    }

    public function test_non_administrator_cannot_access_admin_dashboard(): void
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
            ->get('/admin');

        $response->assertForbidden();
    }
}