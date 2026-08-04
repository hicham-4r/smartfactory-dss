<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Logout;

class RecordAuthenticationLogoutAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Record session logout.
     */
    public function handle(
        Logout $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogService->record(
            action: AuditAction::AuthenticationLoggedOut,
            actor: $event->user,
            auditable: $event->user,
            metadata: [
                'guard' => $event->guard,
            ]
        );
    }
}