<?php

namespace App\Models;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncResourceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpSyncRunResource extends Model
{
    protected $fillable = [
        'erp_sync_run_id',
        'resource',
        'status',
        'pages_processed',
        'records_fetched',
        'records_mapped',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'last_source_updated_at',
        'last_source_version',
        'last_cursor_fingerprint',
        'error_code',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'resource' =>
                ErpResource::class,

            'status' =>
                ErpSyncResourceStatus::class,

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

            'last_source_updated_at' =>
                'immutable_datetime',

            'last_source_version' =>
                'integer',

            'started_at' =>
                'immutable_datetime',

            'finished_at' =>
                'immutable_datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            ErpSyncRun::class,
            'erp_sync_run_id'
        );
    }

    public function failures(): HasMany
    {
        return $this->hasMany(
            ErpSyncFailure::class
        );
    }

    public function isFinished(): bool
    {
        return $this->status->isTerminal();
    }
}