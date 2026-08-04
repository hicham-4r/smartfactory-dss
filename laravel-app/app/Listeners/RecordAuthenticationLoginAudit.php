<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Login;

class RecordAuthenticationLoginAudit
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    /**
     * Record successful authentication.
     */
    public function handle(
        Login $event
    ): void {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogService->record(
            action: AuditAction::AuthenticationSucceeded,
            actor: $event->user,
            auditable: $event->user,
            metadata: [
                'guard' => $event->guard,
                'remember' => $event->remember,
            ]
        );
    }
}