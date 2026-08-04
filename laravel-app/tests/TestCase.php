<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

abstract class TestCase extends BaseTestCase
{
    /**
     * Enable and confirm Fortify two-factor authentication
     * for users accessing administrator-protected routes.
     */
    protected function enableConfirmedTwoFactorAuthentication(
        User $user
    ): User {
        app(
            EnableTwoFactorAuthentication::class
        )($user);

        $user->refresh();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->refresh();
    }
}