<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    /**
     * Audit logs have only a creation timestamp.
     */
    public const UPDATED_AT = null;

    /**
     * Audit records must only be created through AuditLogService.
     *
     * @var list<string>
     */
    protected $guarded = [
        '*',
    ];

    /**
     * User responsible for the audited action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_id'
        );
    }

    /**
     * Convert structured audit fields into safe PHP values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Protect audit history from normal application modification.
     */
    protected static function booted(): void
    {
        static::updating(
            function (): never {
                throw new LogicException(
                    'Audit logs cannot be modified.'
                );
            }
        );

        static::deleting(
            function (): never {
                throw new LogicException(
                    'Audit logs cannot be deleted.'
                );
            }
        );
    }
}