<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecureLoginService
{
    /**
     * A valid non-secret bcrypt hash used when an account does not exist.
     *
     * Checking against a dummy hash reduces timing differences between
     * existing and non-existing accounts.
     */
    private const DUMMY_PASSWORD_HASH =
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    /**
     * Authenticate the incoming Fortify login request.
     *
     * Returning null causes Fortify to display its standard generic
     * authentication failure message.
     */
    public function authenticate(Request $request): ?User
    {
        $email = Str::lower(
            trim((string) $request->input('email'))
        );

        $password = (string) $request->input('password');

        $user = User::query()
            ->where('email', $email)
            ->first();

        /*
         * Always perform a password hash verification, even when the email
         * does not exist, to reduce account-enumeration timing differences.
         */
        $passwordMatches = Hash::check(
            $password,
            $user?->password ?? self::DUMMY_PASSWORD_HASH
        );

        if ($user === null) {
            return null;
        }

        /*
         * Do not reveal whether an account is inactive or locked.
         * Fortify will return the same generic error used for bad credentials.
         */
        if (! $user->canAuthenticate()) {
            return null;
        }

        if (! $passwordMatches) {
            $this->recordFailedAttempt($user);

            return null;
        }

        return $user;
    }

    /**
     * Atomically record a failed password attempt.
     */
    private function recordFailedAttempt(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var User|null $account */
            $account = User::query()
                ->lockForUpdate()
                ->find($user->getKey());

            if ($account === null || ! $account->is_active) {
                return;
            }

            $maximumAttempts = max(
                1,
                (int) config(
                    'security.authentication.max_failed_attempts',
                    5
                )
            );

            $lockoutMinutes = max(
                1,
                (int) config(
                    'security.authentication.lockout_minutes',
                    15
                )
            );

            /*
             * When a previous lock has expired, start a new failed-attempt
             * sequence instead of immediately locking the account again.
             */
            $previousAttempts = $account->locked_until?->isPast()
                ? 0
                : (int) $account->failed_login_count;

            $failedAttempts = min(
                65535,
                $previousAttempts + 1
            );

            $securityAttributes = [
                'failed_login_count' => $failedAttempts,
                'last_failed_login_at' => now(),
            ];

            if ($failedAttempts >= $maximumAttempts) {
                $securityAttributes['locked_until'] = now()
                    ->addMinutes($lockoutMinutes);
            } elseif ($account->locked_until?->isPast()) {
                $securityAttributes['locked_until'] = null;
            }

            $account
                ->forceFill($securityAttributes)
                ->saveQuietly();
        }, 3);
    }
}