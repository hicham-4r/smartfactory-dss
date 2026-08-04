<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

final class RecordTwoFactorDisabledAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function handle(
        TwoFactorAuthenticationDisabled $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogService->record(
            action: AuditAction::TwoFactorDisabled,
            actor: $event->user,
            auditable: $event->user,
            oldValues: [
                'two_factor_enabled' => true,
            ],
            newValues: [
                'two_factor_enabled' => false,
            ]
        );
    }
}