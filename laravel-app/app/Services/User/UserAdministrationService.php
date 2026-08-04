<?php

namespace App\Services\User;

use App\DTOs\User\CreateManagedUserData;
use App\DTOs\User\ManagedUserProvisioningResult;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class UserAdministrationService
{
    private const ROLE_GUARD = 'web';

    private const TEMPORARY_PASSWORD_LENGTH = 20;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Create an active account with exactly one role and a temporary password.
     */
    public function createUser(
        CreateManagedUserData $data,
        User $actor
    ): ManagedUserProvisioningResult {
        $temporaryPassword =
            $this->generateTemporaryPassword();

        return DB::transaction(
            function () use (
                $data,
                $actor,
                $temporaryPassword
            ): ManagedUserProvisioningResult {
                $role = Role::findByName(
                    $data->role->value,
                    self::ROLE_GUARD
                );

                $user = new User();

                $user->forceFill([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => $temporaryPassword,
                    'is_active' => true,
                    'deactivated_at' => null,
                    'password_changed_at' => null,
                    'must_change_password' => true,
                    'failed_login_count' => 0,
                    'last_failed_login_at' => null,
                    'locked_until' => null,
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]);

                $user = $this->users->save($user);

                /*
                 * Exactly one role is assigned. Any existing role assignment
                 * would be removed, although a newly created user has none.
                 */
                $user->syncRoles([$role]);

                $this->auditLogService->record(
                    action: AuditAction::UserCreated,
                    actor: $actor,
                    auditable: $user,
                    newValues: [
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_active' => true,
                        'must_change_password' => true,
                        'role' => $data->role->value,
                    ]
                );

                $this->auditLogService->record(
                    action: AuditAction::UserRolesChanged,
                    actor: $actor,
                    auditable: $user,
                    oldValues: [
                        'roles' => [],
                    ],
                    newValues: [
                        'roles' => [
                            $data->role->value,
                        ],
                    ],
                    metadata: [
                        'source' => 'user-creation',
                    ]
                );

                return new ManagedUserProvisioningResult(
                    user: $user->load('roles'),
                    temporaryPassword: $temporaryPassword
                );
            },
            3
        );
    }

    /**
     * Activate a previously deactivated account.
     */
    public function activateUser(
        User $target,
        User $actor
    ): User {
        return DB::transaction(
            function () use (
                $target,
                $actor
            ): User {
                $account = $this->users->findForUpdate(
                    (int) $target->getKey()
                );

                if ($account->is_active) {
                    throw ValidationException::withMessages([
                        'user' => [
                            'This account is already active.',
                        ],
                    ]);
                }

                $oldValues = [
                    'is_active' => false,
                    'deactivated_at' =>
                        $account->deactivated_at
                            ?->toIso8601String(),
                ];

                $account->forceFill([
                    'is_active' => true,
                    'deactivated_at' => null,
                    'failed_login_count' => 0,
                    'last_failed_login_at' => null,
                    'locked_until' => null,
                    'updated_by' => $actor->getKey(),
                ]);

                $account = $this->users->save($account);

                $this->auditLogService->record(
                    action: AuditAction::UserActivated,
                    actor: $actor,
                    auditable: $account,
                    oldValues: $oldValues,
                    newValues: [
                        'is_active' => true,
                        'deactivated_at' => null,
                    ]
                );

                return $account;
            },
            3
        );
    }

    /**
     * Deactivate an account while protecting administrator continuity.
     */
    public function deactivateUser(
        User $target,
        User $actor
    ): User {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => [
                    'You cannot deactivate your own account.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $target,
                $actor
            ): User {
                $account = $this->users->findForUpdate(
                    (int) $target->getKey()
                );

                if (! $account->is_active) {
                    throw ValidationException::withMessages([
                        'user' => [
                            'This account is already inactive.',
                        ],
                    ]);
                }

                if (
                    $account->hasRole(
                        RoleName::Administrator->value
                    )
                    && $this->users
                        ->activeAdministratorCount() <= 1
                ) {
                    throw ValidationException::withMessages([
                        'user' => [
                            'The final active administrator account '
                            .'cannot be deactivated.',
                        ],
                    ]);
                }

                $deactivatedAt = now();

                $account->forceFill([
                    'is_active' => false,
                    'deactivated_at' => $deactivatedAt,
                    'failed_login_count' => 0,
                    'last_failed_login_at' => null,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                    'updated_by' => $actor->getKey(),
                ]);

                $account = $this->users->save($account);

                $this->auditLogService->record(
                    action: AuditAction::UserDeactivated,
                    actor: $actor,
                    auditable: $account,
                    oldValues: [
                        'is_active' => true,
                        'deactivated_at' => null,
                    ],
                    newValues: [
                        'is_active' => false,
                        'deactivated_at' =>
                            $deactivatedAt->toIso8601String(),
                    ]
                );

                return $account;
            },
            3
        );
    }

    /**
     * Replace a user's password with a one-time temporary password.
     */
    public function resetTemporaryPassword(
        User $target,
        User $actor
    ): ManagedUserProvisioningResult {
        if ($target->is($actor)) {
            throw ValidationException::withMessages([
                'user' => [
                    'Use your personal password-change page '
                    .'to change your own password.',
                ],
            ]);
        }

        $temporaryPassword =
            $this->generateTemporaryPassword();

        return DB::transaction(
            function () use (
                $target,
                $actor,
                $temporaryPassword
            ): ManagedUserProvisioningResult {
                $account = $this->users->findForUpdate(
                    (int) $target->getKey()
                );

                if (! $account->is_active) {
                    throw ValidationException::withMessages([
                        'user' => [
                            'Activate the account before resetting '
                            .'its password.',
                        ],
                    ]);
                }

                $account->forceFill([
                    'password' => $temporaryPassword,
                    'password_changed_at' => null,
                    'must_change_password' => true,
                    'failed_login_count' => 0,
                    'last_failed_login_at' => null,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                    'updated_by' => $actor->getKey(),
                ]);

                $account = $this->users->save($account);

                /*
                 * Password values are never passed to the audit service.
                 */
                $this->auditLogService->record(
                    action: AuditAction::UserPasswordReset,
                    actor: $actor,
                    auditable: $account,
                    newValues: [
                        'must_change_password' => true,
                        'password_changed_at' => null,
                    ],
                    metadata: [
                        'temporary_password_generated' => true,
                    ]
                );

                return new ManagedUserProvisioningResult(
                    user: $account->load('roles'),
                    temporaryPassword: $temporaryPassword
                );
            },
            3
        );
    }

    /**
     * Generate a cryptographically secure password that always satisfies
     * the global password complexity policy.
     */
    private function generateTemporaryPassword(): string
    {
        $lowercase =
            'abcdefghijkmnopqrstuvwxyz';

        $uppercase =
            'ABCDEFGHJKLMNPQRSTUVWXYZ';

        $numbers =
            '23456789';

        $symbols =
            '!@#$%^&*()-_=+';

        $password = [
            $this->randomCharacter($lowercase),
            $this->randomCharacter($uppercase),
            $this->randomCharacter($numbers),
            $this->randomCharacter($symbols),
        ];

        $allCharacters =
            $lowercase
            .$uppercase
            .$numbers
            .$symbols;

        while (
            count($password)
            < self::TEMPORARY_PASSWORD_LENGTH
        ) {
            $password[] = $this->randomCharacter(
                $allCharacters
            );
        }

        /*
         * Fisher-Yates shuffle using random_int rather than str_shuffle.
         */
        for (
            $index = count($password) - 1;
            $index > 0;
            $index--
        ) {
            $swapIndex = random_int(
                0,
                $index
            );

            [
                $password[$index],
                $password[$swapIndex],
            ] = [
                $password[$swapIndex],
                $password[$index],
            ];
        }

        return implode('', $password);
    }

    private function randomCharacter(
        string $characters
    ): string {
        return $characters[
            random_int(
                0,
                strlen($characters) - 1
            )
        ];
    }
}