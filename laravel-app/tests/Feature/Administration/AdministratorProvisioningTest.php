<?php

namespace Tests\Feature\Administration;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\User\AdministratorProvisioningService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdministratorProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_is_created_with_secure_initial_state(): void
    {
        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $temporaryPassword = 'InitialAdmin!2026';

        $administrator = app(
            AdministratorProvisioningService::class
        )->provision(
            'Initial Administrator',
            'ADMIN@SMARTFACTORY.TEST',
            $temporaryPassword
        );

        $this->assertSame(
            'admin@smartfactory.test',
            $administrator->email
        );

        $this->assertTrue(
            Hash::check(
                $temporaryPassword,
                $administrator->password
            )
        );

        $this->assertNotSame(
            $temporaryPassword,
            $administrator->password
        );

        $this->assertTrue(
            $administrator->is_active
        );

        $this->assertTrue(
            $administrator->must_change_password
        );

        $this->assertNull(
            $administrator->password_changed_at
        );

        $this->assertTrue(
            $administrator->hasRole(
                RoleName::Administrator->value
            )
        );
    }

    public function test_existing_account_cannot_be_promoted_by_provisioning_service(): void
    {
        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $existingUser = User::factory()->create([
            'email' => 'existing@smartfactory.test',
        ]);

        try {
            app(
                AdministratorProvisioningService::class
            )->provision(
                'Existing User',
                'EXISTING@SMARTFACTORY.TEST',
                'InitialAdmin!2026'
            );

            $this->fail(
                'A duplicate administrator account was unexpectedly created.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'email',
                $exception->errors()
            );
        }

        $existingUser->refresh();

        $this->assertFalse(
            $existingUser->hasRole(
                RoleName::Administrator->value
            )
        );

        $this->assertSame(
            1,
            User::query()
                ->where(
                    'email',
                    'existing@smartfactory.test'
                )
                ->count()
        );
    }

    public function test_administrator_cannot_be_created_without_seeded_role(): void
    {
        try {
            app(
                AdministratorProvisioningService::class
            )->provision(
                'Initial Administrator',
                'admin@smartfactory.test',
                'InitialAdmin!2026'
            );

            $this->fail(
                'Administrator creation succeeded without a configured role.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'role',
                $exception->errors()
            );
        }

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@smartfactory.test',
        ]);
    }

    public function test_secure_administrator_command_is_registered(): void
    {
        $this->assertArrayHasKey(
            'smartfactory:admin:create',
            Artisan::all()
        );
    }
}