<?php

namespace App\Services\User;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdministratorProvisioningService
{
    private const GUARD = 'web';

    /**
     * Provision the first controlled administrator account.
     *
     * Existing accounts are never automatically promoted because doing so
     * could create an unintended privilege-escalation path.
     */
    public function provision(
        string $name,
        string $email,
        string $temporaryPassword
    ): User {
        $normalizedName = trim($name);

        $normalizedEmail = Str::lower(
            trim($email)
        );

        return DB::transaction(
            function () use (
                $normalizedName,
                $normalizedEmail,
                $temporaryPassword
            ): User {
                if (
                    User::query()
                        ->where('email', $normalizedEmail)
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'email' => [
                            'An account already exists for this email address. '
                            .'Existing accounts cannot be promoted by this command.',
                        ],
                    ]);
                }

                $administratorRole = Role::query()
                    ->where(
                        'name',
                        RoleName::Administrator->value
                    )
                    ->where('guard_name', self::GUARD)
                    ->first();

                if ($administratorRole === null) {
                    throw ValidationException::withMessages([
                        'role' => [
                            'The administrator role has not been configured. '
                            .'Run the roles and permissions seeder first.',
                        ],
                    ]);
                }

                $administrator = new User();

                /*
                 * forceFill is intentional because security attributes are
                 * excluded from the model's normal mass-assignment list.
                 */
                $administrator->forceFill([
                    'name' => $normalizedName,
                    'email' => $normalizedEmail,
                    'password' => $temporaryPassword,
                    'is_active' => true,
                    'deactivated_at' => null,
                    'password_changed_at' => null,
                    'must_change_password' => true,
                    'failed_login_count' => 0,
                    'last_failed_login_at' => null,
                    'locked_until' => null,
                    'created_by' => null,
                    'updated_by' => null,
                ]);

                $administrator->save();

                $administrator->assignRole(
                    $administratorRole
                );

                return $administrator->refresh();
            },
            3
        );
    }
}