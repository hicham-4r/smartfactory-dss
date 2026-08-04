<?php

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    private const REDACTED = '[REDACTED]';

    /**
     * Values that must never be persisted in audit logs.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'current_password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_codes',
        'authorization',
        'cookie',
        'api_token',
        'erp_api_token',
        'erp_failure_key',
        'secret',
    ];

    /**
     * Fields containing the word "password" that are safe metadata,
     * not credential values.
     *
     * @var list<string>
     */
    private const SAFE_PASSWORD_METADATA_KEYS = [
        'must_change_password',
        'password_changed_at',
    ];

    /**
     * Append a new security audit record.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     * @param array<string, mixed>|null $metadata
     */
    public function record(
        AuditAction|string $action,
        ?User $actor = null,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?Request $request = null
    ): AuditLog {
        $request ??= $this->currentRequest();

        $requestId = $request?->attributes->get(
            'request_id'
        );

        $auditLog = new AuditLog();

        $auditLog->forceFill([
            'event_id' => (string) Str::uuid(),

            'request_id' => is_string($requestId)
                ? $requestId
                : null,

            'actor_id' => $actor?->getKey(),

            'action' => $action instanceof AuditAction
                ? $action->value
                : mb_substr($action, 0, 150),

            'auditable_type' => $auditable?->getMorphClass(),

            'auditable_id' => $auditable !== null
                ? (string) $auditable->getKey()
                : null,

            'old_values' => $this->sanitize(
                $oldValues
            ),

            'new_values' => $this->sanitize(
                $newValues
            ),

            'metadata' => $this->sanitize(
                $metadata
            ),

            'ip_address' => $request?->ip(),

            'user_agent' => $this->limitUserAgent(
                $request?->userAgent()
            ),

            'occurred_at' => now(),
        ]);

        $auditLog->save();

        return $auditLog;
    }

    /**
     * Remove secret and credential values recursively.
     *
     * @param array<string, mixed>|null $values
     *
     * @return array<string, mixed>|null
     */
    private function sanitize(
        ?array $values
    ): ?array {
        if ($values === null) {
            return null;
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            $keyString = (string) $key;

            if ($this->isSensitiveKey($keyString)) {
                $sanitized[$keyString] = self::REDACTED;

                continue;
            }

            $sanitized[$keyString] = is_array($value)
                ? $this->sanitize($value)
                : $value;
        }

        return $sanitized;
    }

    /**
     * Determine whether a field may contain secret material.
     */
    private function isSensitiveKey(
        string $key
    ): bool {
        $normalizedKey = Str::lower(
            trim($key)
        );

        /*
         * These fields describe password security state but never contain
         * actual password material.
         */
        if (
            in_array(
                $normalizedKey,
                self::SAFE_PASSWORD_METADATA_KEYS,
                true
            )
        ) {
            return false;
        }

        if (
            in_array(
                $normalizedKey,
                self::SENSITIVE_KEYS,
                true
            )
        ) {
            return true;
        }

        return Str::endsWith(
            $normalizedKey,
            [
                '_password',
                '_token',
                '_secret',
                '_recovery_codes',
                '_failure_key',
            ]
        );
    }

    /**
     * Retrieve the active HTTP request when one exists.
     */
    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request
            ? $request
            : null;
    }

    /**
     * Prevent excessively large user-agent values.
     */
    private function limitUserAgent(
        ?string $userAgent
    ): ?string {
        if ($userAgent === null) {
            return null;
        }

        return mb_substr(
            $userAgent,
            0,
            1000
        );
    }
}