<?php

namespace App\Models;

use App\Enums\ERP\ErpResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpSyncState extends Model
{
    protected $fillable = [
        'source_system',
        'resource',
        'last_successful_sync_at',
        'last_source_updated_at',
        'last_source_version',
        'resume_page',
        'resume_cursor',
        'resume_cursor_fingerprint',
        'last_run_id',
        'lock_owner',
        'lock_acquired_at',
        'consecutive_failures',
        'last_error_code',
        'last_error_message',
    ];

    /*
     * Do not expose the decrypted cursor through serialization.
     */
    protected $hidden = [
        'resume_cursor',
    ];

    protected function casts(): array
    {
        return [
            'resource' =>
                ErpResource::class,

            'last_successful_sync_at' =>
                'immutable_datetime',

            'last_source_updated_at' =>
                'immutable_datetime',

            'last_source_version' =>
                'integer',

            'resume_page' =>
                'integer',

            /*
             * Encryption uses APP_KEY.
             */
            'resume_cursor' =>
                'encrypted',

            'lock_acquired_at' =>
                'immutable_datetime',

            'consecutive_failures' =>
                'integer',
        ];
    }

    public function lastRun(): BelongsTo
    {
        return $this->belongsTo(
            ErpSyncRun::class,
            'last_run_id'
        );
    }

    public function hasLease(): bool
    {
        return $this->lock_owner !== null
            && $this->lock_acquired_at !== null;
    }
}