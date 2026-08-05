<?php

namespace App\Services\Notifications;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class NotificationRecipientResolver
{
    /**
     * Find active users who possess a given permission.
     *
     * Notification delivery is a secondary operation and must never
     * interrupt production, ERP synchronization, or maintenance logic.
     *
     * Some isolated tests and maintenance commands intentionally run
     * without seeding the authorization catalog. In that situation,
     * there are simply no eligible notification recipients.
     *
     * @return Collection<int, User>
     */
    public function usersWithPermission(
        PermissionName|string $permission
    ): Collection {
        $permissionValue =
            $permission instanceof PermissionName
                ? $permission->value
                : trim($permission);

        if ($permissionValue === '') {
            return new Collection();
        }

        $guardName = $this->guardName();

        $permissionExists = Permission::query()
            ->where('name', $permissionValue)
            ->where('guard_name', $guardName)
            ->exists();

        if (! $permissionExists) {
            return new Collection();
        }

        /*
         * Spatie's permission scope accepts a permission followed by
         * a boolean "without" flag. It does not accept a guard name
         * as its second argument. Passing "web" there is truthy and
         * selects users without the permission.
         *
         * The permission existence check above already validates the
         * configured guard, while the User model resolves its default
         * guard internally.
         */
        return User::query()
            ->where('is_active', true)
            ->permission(
                $permissionValue
            )
            ->orderBy('users.id')
            ->get();
    }

    /**
     * Find active users who possess a given role.
     *
     * A missing role returns an empty recipient collection instead of
     * throwing RoleDoesNotExist and interrupting the main operation.
     *
     * @return Collection<int, User>
     */
    public function usersWithRole(
        RoleName|string $role
    ): Collection {
        $roleValue = $role instanceof RoleName
            ? $role->value
            : trim($role);

        if ($roleValue === '') {
            return new Collection();
        }

        $guardName = $this->guardName();

        $roleExists = Role::query()
            ->where('name', $roleValue)
            ->where('guard_name', $guardName)
            ->exists();

        if (! $roleExists) {
            return new Collection();
        }

        return User::query()
            ->where('is_active', true)
            ->role(
                $roleValue,
                $guardName
            )
            ->orderBy('users.id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function administrators(): Collection
    {
        return $this->usersWithRole(
            RoleName::Administrator
        );
    }

    /**
     * Combine recipient groups while removing duplicate users.
     *
     * @param iterable<User> ...$groups
     *
     * @return Collection<int, User>
     */
    public function unique(
        iterable ...$groups
    ): Collection {
        $users = [];

        foreach ($groups as $group) {
            foreach ($group as $user) {
                if (! $user->is_active) {
                    continue;
                }

                $users[
                    (int) $user->getKey()
                ] = $user;
            }
        }

        ksort($users);

        return new Collection(
            array_values($users)
        );
    }

    /**
     * Resolve the authorization guard used by the application.
     */
    private function guardName(): string
    {
        $guardName = config(
            'auth.defaults.guard',
            'web'
        );

        if (! is_string($guardName)) {
            return 'web';
        }

        $guardName = trim($guardName);

        return $guardName !== ''
            ? $guardName
            : 'web';
    }
}