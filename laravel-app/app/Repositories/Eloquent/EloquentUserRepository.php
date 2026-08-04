<?php

namespace App\Repositories\Eloquent;

use App\Enums\RoleName;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentUserRepository implements
    UserRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateForAdministration(
        int $perPage = 15
    ): LengthAwarePaginator {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('email')
            ->paginate($perPage);
    }

    public function findForUpdate(
        int $userId
    ): User {
        return User::query()
            ->lockForUpdate()
            ->findOrFail($userId);
    }

    public function save(
        User $user
    ): User {
        $user->save();

        return $user->refresh();
    }

    public function activeAdministratorCount(): int
    {
        return User::query()
            ->role(RoleName::Administrator->value)
            ->where('is_active', true)
            ->count();
    }
}