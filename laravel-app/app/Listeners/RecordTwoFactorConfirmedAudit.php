<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

final class RecordTwoFactorConfirmedAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function handle(
        TwoFactorAuthenticationConfirmed $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->refresh();

        $this->auditLogService->record(
            action: AuditAction::TwoFactorConfirmed,
            actor: $event->user,
            auditable: $event->user,
            newValues: [
                'two_factor_enabled' => true,
                'two_factor_confirmed_at' =>
                    $event->user
                        ->two_factor_confirmed_at
                        ?->toIso8601String(),
            ]
        );
    }
}