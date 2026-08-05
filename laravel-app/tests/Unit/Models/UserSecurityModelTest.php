<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

class UserSecurityModelTest extends TestCase
{
    public function test_security_fields_are_not_mass_assignable(): void
    {
        $user = new User();

        $user->fill([
            'name' => 'Security Test',
            'email' => 'security@example.com',
            'password' => 'SmartFactory!2026',
            'is_active' => false,
            'failed_login_count' => 99,
            'locked_until' => now()->addHour(),
        ]);

        $this->assertSame(
            ['name', 'email', 'password'],
            $user->getFillable()
        );

        $this->assertNull($user->getAttribute('is_active'));
        $this->assertNull($user->getAttribute('failed_login_count'));
        $this->assertNull($user->getAttribute('locked_until'));
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        $user = new User();

        $user->email = '  ADMIN@SMARTFACTORY.TEST  ';

        $this->assertSame(
            'admin@smartfactory.test',
            $user->email
        );
    }

    public function test_locked_account_cannot_authenticate(): void
    {
        $user = new User();

        $user->forceFill([
            'is_active' => true,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->assertTrue($user->isLocked());
        $this->assertFalse($user->canAuthenticate());
    }

    public function test_active_unlocked_account_can_authenticate(): void
    {
        $user = new User();

        $user->forceFill([
            'is_active' => true,
            'locked_until' => null,
        ]);

        $this->assertFalse($user->isLocked());
        $this->assertTrue($user->canAuthenticate());
    }

    public function test_inactive_account_cannot_authenticate(): void
    {
        $user = new User();

        $user->forceFill([
            'is_active' => false,
            'locked_until' => null,
        ]);

        $this->assertFalse($user->canAuthenticate());
    }
}