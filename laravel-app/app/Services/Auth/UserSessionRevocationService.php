<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserSessionRevocationService
{
    /**
     * Delete every database-backed session belonging to one user.
     *
     * Non-database session drivers do not expose a reliable user_id
     * column, so the service safely performs no operation for them.
     */
    public function revokeAllFor(
        User $user
    ): int {
        if (
            config('session.driver')
            !== 'database'
        ) {
            return 0;
        }

        $connection = config(
            'session.connection'
        );

        $table = (string) config(
            'session.table',
            'sessions'
        );

        return DB::connection(
            is_string($connection)
            && $connection !== ''
                ? $connection
                : null
        )
            ->table($table)
            ->where(
                'user_id',
                $user->getKey()
            )
            ->delete();
    }
}