<?php

namespace App\Models;

use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpSyncRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'source_system',
        'trigger',
        'status',
        'initiated_by_user_id',
        'request_id',
        'requested_resources',
        'pages_processed',
        'records_fetched',
        'records_mapped',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger' =>
                ErpSyncTrigger::class,

            'status' =>
                ErpSyncRunStatus::class,

            'requested_resources' =>
                'array',

            'pages_processed' =>
                'integer',

            'records_fetched' =>
                'integer',

            'records_mapped' =>
                'integer',

            'records_created' =>
                'integer',

            'records_updated' =>
                'integer',

            'records_skipped' =>
                'integer',

            'records_failed' =>
                'integer',

            'started_at' =>
                'immutable_datetime',

            'finished_at' =>
                'immutable_datetime',
        ];
    }

    /**
     * Synchronization resources in the order in which they were
     * created and processed.
     */
    public function resources(): HasMany
    {
        return $this
            ->hasMany(
                ErpSyncRunResource::class
            )
            ->orderBy(
                'erp_sync_run_resources.id'
            );
    }

    /**
     * Synchronization failures in chronological creation order.
     */
    public function failures(): HasMany
    {
        return $this
            ->hasMany(
                ErpSyncFailure::class
            )
            ->orderBy(
                'erp_sync_failures.id'
            );
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'initiated_by_user_id'
        );
    }

    public function isFinished(): bool
    {
        return $this
            ->status
            ->isTerminal();
    }
}