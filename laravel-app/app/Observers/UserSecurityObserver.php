<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Auth\UserSessionRevocationService;

final class UserSecurityObserver
{
    public function __construct(
        private readonly UserSessionRevocationService
            $sessionRevocationService
    ) {
    }

    /**
     * Revoke sessions after security-sensitive account changes.
     */
    public function updated(
        User $user
    ): void {
        $becameInactive =
            $user->wasChanged('is_active')
            && ! $user->is_active;

        $temporaryPasswordIssued =
            $user->wasChanged('password')
            && $user->must_change_password;

        if (
            $becameInactive
            || $temporaryPasswordIssued
        ) {
            $this->sessionRevocationService
                ->revokeAllFor($user);
        }
    }
}