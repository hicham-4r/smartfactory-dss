<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecureAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_authenticate_securely(): void
    {
        $user = User::factory()->create([
            'email' => 'active@smartfactory.test',
            'password' => 'SmartFactory!2026',
        ]);

        $user->forceFill([
            'is_active' => true,
            'failed_login_count' => 3,
            'last_failed_login_at' => now()->subMinute(),
            'locked_until' => null,
        ])->save();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '10.0.0.10',
            ])
            ->post('/login', [
                'email' => 'ACTIVE@SMARTFACTORY.TEST',
                'password' => 'SmartFactory!2026',
            ]);

        $response->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertSame('10.0.0.10', $user->last_login_ip);
        $this->assertSame(0, $user->failed_login_count);
        $this->assertNull($user->last_failed_login_at);
        $this->assertNull($user->locked_until);
    }

    public function test_invalid_password_increments_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid-password@smartfactory.test',
            'password' => 'SmartFactory!2026',
        ]);

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '10.0.0.11',
            ])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'Incorrect!2026',
            ]);

        $response->assertSessionHasErrors([
            'email' => trans('auth.failed'),
        ]);

        $this->assertGuest();

        $user->refresh();

        $this->assertSame(1, $user->failed_login_count);
        $this->assertNotNull($user->last_failed_login_at);
    }

    public function test_inactive_account_is_rejected_generically(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@smartfactory.test',
            'password' => 'SmartFactory!2026',
        ]);

        $user->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
        ])->save();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '10.0.0.12',
            ])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'SmartFactory!2026',
            ]);

        $response->assertSessionHasErrors([
            'email' => trans('auth.failed'),
        ]);

        $this->assertGuest();
    }

    public function test_temporarily_locked_account_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'locked@smartfactory.test',
            'password' => 'SmartFactory!2026',
        ]);

        $user->forceFill([
            'is_active' => true,
            'failed_login_count' => 5,
            'locked_until' => now()->addMinutes(10),
        ])->save();

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '10.0.0.13',
            ])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'SmartFactory!2026',
            ]);

        $response->assertSessionHasErrors([
            'email' => trans('auth.failed'),
        ]);

        $this->assertGuest();
    }

    public function test_account_is_locked_after_repeated_failures(): void
    {
        $user = User::factory()->create([
            'email' => 'repeated-failures@smartfactory.test',
            'password' => 'SmartFactory!2026',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '10.0.0.14',
                ])
                ->post('/login', [
                    'email' => $user->email,
                    'password' => 'Incorrect!2026',
                ]);

            $response->assertSessionHasErrors('email');
        }

        $user->refresh();

        $this->assertSame(5, $user->failed_login_count);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
        $this->assertFalse($user->canAuthenticate());
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this
                ->withServerVariables([
                    'REMOTE_ADDR' => '10.0.0.15',
                ])
                ->post('/login', [
                    'email' => 'unknown@smartfactory.test',
                    'password' => 'Incorrect!2026',
                ]);

            $response->assertSessionHasErrors('email');
        }

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '10.0.0.15',
            ])
            ->post('/login', [
                'email' => 'unknown@smartfactory.test',
                'password' => 'Incorrect!2026',
            ]);

        $response->assertStatus(429);
        $this->assertGuest();
    }
}