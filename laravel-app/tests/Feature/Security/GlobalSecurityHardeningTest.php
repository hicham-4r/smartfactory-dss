<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Auth\UserSessionRevocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_responses_include_security_headers(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->assertHeader(
                'X-Frame-Options',
                'DENY'
            )
            ->assertHeader(
                'Referrer-Policy',
                'same-origin'
            )
            ->assertHeader(
                'Cross-Origin-Opener-Policy',
                'same-origin'
            )
            ->assertHeader(
                'Cross-Origin-Resource-Policy',
                'same-origin'
            )
            ->assertHeader(
                'X-Permitted-Cross-Domain-Policies',
                'none'
            );

        $this->assertNotEmpty(
            $response->headers->get(
                'Permissions-Policy'
            )
        );
    }

    public function test_authenticated_html_is_not_cacheable(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();

        $cacheControl = (string)
            $response->headers->get(
                'Cache-Control'
            );

        foreach (
            [
                'no-store',
                'no-cache',
                'must-revalidate',
                'private',
                'max-age=0',
            ]
            as $directive
        ) {
            $this->assertStringContainsString(
                $directive,
                $cacheControl
            );
        }

        $response
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');
    }

    public function test_inactive_authenticated_user_is_logged_out(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user);

        /*
         * saveQuietly avoids testing the session-revocation observer here.
         * This test isolates the request middleware behavior.
         */
        $user->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->saveQuietly();

        $response = $this->get('/dashboard');

        $response
            ->assertRedirect('/login')
            ->assertSessionHas(
                'status',
                'Your session is no longer valid. Please sign in again.'
            );

        $this->assertGuest();
    }

    public function test_locked_authenticated_user_is_logged_out(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'locked_until' => null,
        ]);

        $this->actingAs($user);

        $user->forceFill([
            'locked_until' => now()->addMinutes(15),
        ])->saveQuietly();

        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_session_service_revokes_only_target_user_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);

        $target = User::factory()->create();
        $other = User::factory()->create();

        $this->insertSession(
            'target-session-1',
            $target
        );

        $this->insertSession(
            'target-session-2',
            $target
        );

        $this->insertSession(
            'other-session',
            $other
        );

        $deleted = app(
            UserSessionRevocationService::class
        )->revokeAllFor($target);

        $this->assertSame(2, $deleted);

        $this->assertDatabaseMissing(
            'sessions',
            [
                'id' => 'target-session-1',
            ]
        );

        $this->assertDatabaseMissing(
            'sessions',
            [
                'id' => 'target-session-2',
            ]
        );

        $this->assertDatabaseHas(
            'sessions',
            [
                'id' => 'other-session',
                'user_id' => $other->getKey(),
            ]
        );
    }

    public function test_deactivation_observer_revokes_database_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);

        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->insertSession(
            'deactivated-user-session',
            $user
        );

        $user->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->save();

        $this->assertDatabaseMissing(
            'sessions',
            [
                'id' =>
                    'deactivated-user-session',
            ]
        );
    }

    public function test_temporary_password_observer_revokes_database_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);

        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->insertSession(
            'password-reset-session',
            $user
        );

        $user->forceFill([
            'password' => 'TemporaryReset!2026',
            'must_change_password' => true,
            'password_changed_at' => null,
        ])->save();

        $this->assertDatabaseMissing(
            'sessions',
            [
                'id' => 'password-reset-session',
            ]
        );
    }

    public function test_non_security_profile_update_does_not_revoke_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);

        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->insertSession(
            'profile-update-session',
            $user
        );

        $user->forceFill([
            'name' => 'Updated Display Name',
        ])->save();

        $this->assertDatabaseHas(
            'sessions',
            [
                'id' => 'profile-update-session',
                'user_id' => $user->getKey(),
            ]
        );
    }

    public function test_secure_configuration_defaults_are_active(): void
    {
        $this->assertSame(
            900,
            (int) config('auth.password_timeout')
        );

        $this->assertSame(
            60,
            (int) config('session.lifetime')
        );

        $this->assertTrue(
            (bool) config('session.encrypt')
        );

        $this->assertTrue(
            (bool) config('session.secure')
        );

        $this->assertTrue(
            (bool) config('session.http_only')
        );

        $this->assertSame(
            'lax',
            config('session.same_site')
        );
    }

    private function insertSession(
        string $sessionId,
        User $user
    ): void {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Security Test',
            'payload' => base64_encode(
                'security-test-session'
            ),
            'last_activity' => now()->timestamp,
        ]);
    }
}