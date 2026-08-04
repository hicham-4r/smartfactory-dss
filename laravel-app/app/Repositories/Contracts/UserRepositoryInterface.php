<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * Return users for the administration interface.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateForAdministration(
        int $perPage = 15
    ): LengthAwarePaginator;

    /**
     * Find and lock a user inside a database transaction.
     */
    public function findForUpdate(
        int $userId
    ): User;

    /**
     * Persist a user.
     */
    public function save(
        User $user
    ): User;

    /**
     * Count active administrator accounts.
     */
    public function activeAdministratorCount(): int;
}