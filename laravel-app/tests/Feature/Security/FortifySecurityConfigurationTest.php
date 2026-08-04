<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Tests\TestCase;

class FortifySecurityConfigurationTest extends TestCase
{
    public function test_public_registration_is_disabled(): void
    {
        $this->assertNotContains(
            Features::registration(),
            config('fortify.features', [])
        );

        $this->assertFalse(Route::has('register'));
    }

    public function test_passkeys_are_disabled(): void
    {
        $this->assertNotContains(
            Features::passkeys(),
            config('fortify.features', [])
        );
    }

    public function test_password_reset_is_enabled(): void
    {
        $this->assertContains(
            Features::resetPasswords(),
            config('fortify.features', [])
        );

        $this->assertTrue(Route::has('password.request'));
    }

    public function test_weak_password_is_rejected(): void
    {
        $validator = Validator::make(
            [
                'password' => 'Weak123',
                'password_confirmation' => 'Weak123',
            ],
            [
                'password' => [
                    'required',
                    'confirmed',
                    Password::default(),
                ],
            ]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_strong_password_is_accepted(): void
    {
        $password = 'SmartFactory!2026';

        $validator = Validator::make(
            [
                'password' => $password,
                'password_confirmation' => $password,
            ],
            [
                'password' => [
                    'required',
                    'confirmed',
                    Password::default(),
                ],
            ]
        );

        $this->assertFalse(
            $validator->fails(),
            implode(' ', $validator->errors()->all())
        );
    }
}