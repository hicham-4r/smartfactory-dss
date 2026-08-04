<?php

namespace Tests\Feature\Security;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Tests\TestCase;

class AdministratorTwoFactorAuthenticationTest extends
    TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );
    }

    public function test_administrator_without_two_factor_is_redirected_to_setup(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get('/admin');

        $response->assertRedirect(
            '/security/two-factor'
        );
    }

    public function test_operator_does_not_require_two_factor_authentication(): void
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
            ->get('/dashboard');

        $response->assertOk();
    }

    public function test_administrator_can_view_two_factor_setup_page(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get('/security/two-factor');

        $response
            ->assertOk()
            ->assertSeeText(
                'Two-factor authentication is required'
            )
            ->assertHeader(
                'Pragma',
                'no-cache'
            );
    }

    public function test_enabling_two_factor_requires_password_confirmation(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->post(
                '/user/two-factor-authentication'
            );

        $response->assertRedirect(
            '/user/confirm-password'
        );
    }

    public function test_confirmed_two_factor_allows_administrator_dashboard(): void
    {
        $administrator =
            $this->administrator();

        app(
            EnableTwoFactorAuthentication::class
        )($administrator);

        $administrator->refresh();

        $administrator->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this
            ->actingAs($administrator)
            ->get('/admin');

        $response
            ->assertOk()
            ->assertSeeText(
                'Administrator operations'
            );
    }

    public function test_two_factor_challenge_view_is_registered_and_not_cacheable(): void
    {
        $administrator =
            $this->administrator();

        app(
            EnableTwoFactorAuthentication::class
        )($administrator);

        $administrator->refresh();

        $administrator->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this
            ->withSession([
                'login.id' =>
                    $administrator->getKey(),
            ])
            ->get('/two-factor-challenge');

        $response
            ->assertOk()
            ->assertSeeText(
                'Verify your secure sign-in'
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
            'private',
            $cacheControl
        );
    }

    public function test_two_factor_security_events_are_audited_without_secrets(): void
    {
        $administrator =
            $this->administrator();

        event(
            new TwoFactorAuthenticationConfirmed(
                $administrator
            )
        );

        event(
            new RecoveryCodesGenerated(
                $administrator
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $administrator->getKey(),

                'action' =>
                    AuditAction
                        ::TwoFactorConfirmed
                        ->value,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $administrator->getKey(),

                'action' =>
                    AuditAction
                        ::TwoFactorRecoveryCodesRegenerated
                        ->value,
            ]
        );

        $auditValues = \App\Models\AuditLog::query()
            ->get()
            ->flatMap(
                fn ($auditLog): array => [
                    $auditLog->old_values,
                    $auditLog->new_values,
                    $auditLog->metadata,
                ]
            )
            ->filter()
            ->toJson();

        $this->assertStringNotContainsString(
            'two_factor_secret',
            $auditValues
        );

        $this->assertStringNotContainsString(
            'recovery_code',
            $auditValues
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

        return $administrator;
    }
}