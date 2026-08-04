<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_page(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeText('Sign in to your account')
            ->assertSee('name="_token"', false)
            ->assertDontSeeText('Create account');
    }

    public function test_guest_can_view_forgot_password_page(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertOk()
            ->assertSeeText('Reset your password')
            ->assertSeeText(
                'If a matching account exists'
            );
    }

    public function test_guest_can_view_reset_password_page(): void
    {
        $response = $this->get(
            '/reset-password/test-token'
            .'?email=user%40smartfactory.test'
        );

        $response
            ->assertOk()
            ->assertSeeText('Choose a new password')
            ->assertSee('name="token"', false)
            ->assertSee('value="test-token"', false);
    }

    public function test_guest_is_redirected_from_password_confirmation(): void
    {
        $response = $this->get('/user/confirm-password');

        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Authorized User',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeText('Authorized User')
            ->assertSeeText(
                'Shared role-aware overview'
            );
    }
}