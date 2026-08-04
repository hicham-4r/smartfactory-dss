<?php

namespace App\Services\Auth;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MandatoryPasswordChangeService
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Replace a temporary password and unlock normal application access.
     */
    public function changePassword(
        User $user,
        string $newPassword
    ): void {
        DB::transaction(
            function () use (
                $user,
                $newPassword
            ): void {
                $changedAt = now();

                $oldValues = [
                    'must_change_password' =>
                        (bool) $user->must_change_password,

                    'password_changed_at' =>
                        $user->password_changed_at?->toIso8601String(),
                ];

                $user->forceFill([
                    'password' => $newPassword,
                    'password_changed_at' => $changedAt,
                    'must_change_password' => false,
                    'failed_login_count' => 0,
                    'last_failed_login_at' => null,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                    'updated_by' => $user->getKey(),
                ])->save();

                /*
                 * Password values are deliberately excluded.
                 */
                $this->auditLogService->record(
                    action: AuditAction::MandatoryPasswordChanged,
                    actor: $user,
                    auditable: $user,
                    oldValues: $oldValues,
                    newValues: [
                        'must_change_password' => false,

                        'password_changed_at' =>
                            $changedAt->toIso8601String(),
                    ],
                    metadata: [
                        'source' =>
                            'mandatory-password-change',
                    ]
                );
            },
            3
        );
    }
}