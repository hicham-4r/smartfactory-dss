<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Laravel\Fortify\Events\RecoveryCodesGenerated;

final class RecordTwoFactorRecoveryCodesAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    public function handle(
        RecoveryCodesGenerated $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogService->record(
            action:
                AuditAction
                    ::TwoFactorRecoveryCodesRegenerated,
            actor: $event->user,
            auditable: $event->user,
            metadata: [
                'codes_regenerated' => true,
            ]
        );
    }
}