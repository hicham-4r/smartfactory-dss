<?php

namespace Tests\Feature\Security;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class AuditLogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_http_response_receives_a_request_identifier(): void
    {
        $response = $this->get('/login');

        $response->assertOk();

        $requestId = (string) $response->headers->get(
            'X-Request-ID'
        );

        $this->assertNotSame(
            '',
            $requestId
        );

        $this->assertTrue(
            Str::isUuid($requestId)
        );
    }

    public function test_sensitive_values_are_redacted(): void
    {
        $user = User::factory()->create();

        $auditLog = app(
            AuditLogService::class
        )->record(
            action: 'test.security.redaction',
            actor: $user,
            auditable: $user,
            oldValues: [
                'name' => 'Old Name',
                'password' => 'NeverStoreThis',
                'nested' => [
                    'api_token' => 'SecretToken',
                ],
            ],
            newValues: [
                'name' => 'New Name',
                'password_confirmation' =>
                    'NeverStoreThisEither',
            ]
        );

        $this->assertSame(
            '[REDACTED]',
            $auditLog->old_values['password']
        );

        $this->assertSame(
            '[REDACTED]',
            $auditLog->old_values['nested']['api_token']
        );

        $this->assertSame(
            '[REDACTED]',
            $auditLog->new_values['password_confirmation']
        );

        $this->assertSame(
            'New Name',
            $auditLog->new_values['name']
        );
    }

    public function test_successful_login_is_audited(): void
    {
        $user = User::factory()->create([
            'email' => 'audit-login@smartfactory.test',
            'password' => 'SmartFactory!2026',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'SmartFactory!2026',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'action' =>
                AuditAction::AuthenticationSucceeded->value,
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => (string) $user->getKey(),
        ]);

        $auditLog = AuditLog::query()
            ->where(
                'action',
                AuditAction::AuthenticationSucceeded->value
            )
            ->firstOrFail();

        $this->assertNotNull(
            $auditLog->request_id
        );

        $this->assertTrue(
            Str::isUuid($auditLog->request_id)
        );
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/logout');

        $response->assertRedirect('/');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'action' =>
                AuditAction::AuthenticationLoggedOut->value,
        ]);
    }

    public function test_mandatory_password_change_is_audited_without_password_values(): void
    {
        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $user = User::factory()->create([
            'password' => 'TemporaryPassword!2026',
            'is_active' => true,
            'must_change_password' => true,
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

        $response->assertRedirect('/dashboard');

        $auditLog = AuditLog::query()
            ->where(
                'action',
                AuditAction::MandatoryPasswordChanged->value
            )
            ->firstOrFail();

        $this->assertSame(
            $user->getKey(),
            $auditLog->actor_id
        );

        $this->assertArrayNotHasKey(
            'password',
            $auditLog->old_values ?? []
        );

        $this->assertArrayNotHasKey(
            'password',
            $auditLog->new_values ?? []
        );

        $this->assertFalse(
            $auditLog->new_values['must_change_password']
        );
    }

    public function test_existing_audit_log_cannot_be_updated(): void
    {
        $auditLog = app(
            AuditLogService::class
        )->record(
            action: 'test.audit.created'
        );

        try {
            $auditLog->action = 'test.audit.tampered';

            $auditLog->save();

            $this->fail(
                'An audit record was unexpectedly modified.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Audit logs cannot be modified.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->getKey(),
            'action' => 'test.audit.created',
        ]);
    }

    public function test_existing_audit_log_cannot_be_deleted(): void
    {
        $auditLog = app(
            AuditLogService::class
        )->record(
            action: 'test.audit.created'
        );

        try {
            $auditLog->delete();

            $this->fail(
                'An audit record was unexpectedly deleted.'
            );
        } catch (LogicException $exception) {
            $this->assertSame(
                'Audit logs cannot be deleted.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->getKey(),
            'action' => 'test.audit.created',
        ]);
    }
}