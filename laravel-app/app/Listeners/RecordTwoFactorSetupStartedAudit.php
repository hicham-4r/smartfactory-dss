<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

final class RecordTwoFactorSetupStartedAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function handle(
        TwoFactorAuthenticationEnabled $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogService->record(
            action: AuditAction::TwoFactorSetupStarted,
            actor: $event->user,
            auditable: $event->user,
            newValues: [
                'two_factor_pending' => true,
            ],
            metadata: [
                'source' => 'fortify',
            ]
        );
    }
}