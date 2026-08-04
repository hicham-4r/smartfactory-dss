<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordSuccessfulLogin
{
    public function __construct(
        private readonly Request $request
    ) {
    }

    /**
     * Record successful login metadata and clear previous failures.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user
            ->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $this->request->ip(),
                'failed_login_count' => 0,
                'last_failed_login_at' => null,
                'locked_until' => null,
            ])
            ->saveQuietly();
    }
}