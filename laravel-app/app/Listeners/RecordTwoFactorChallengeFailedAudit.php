<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

final class RecordTwoFactorChallengeFailedAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function handle(
        TwoFactorAuthenticationFailed $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogService->record(
            action: AuditAction::TwoFactorChallengeFailed,
            actor: $event->user,
            auditable: $event->user,
            metadata: [
                'result' => 'rejected',
            ]
        );
    }
}