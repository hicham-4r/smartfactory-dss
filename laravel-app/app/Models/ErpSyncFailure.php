<?php

namespace App\Models;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncFailureStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpSyncFailure extends Model
{
    protected $fillable = [
        'erp_sync_run_id',
        'erp_sync_run_resource_id',
        'resource',
        'stage',
        'external_id',
        'page',
        'cursor_fingerprint',
        'error_code',
        'error_message',
        'retryable',
        'safe_context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'resource' =>
                ErpResource::class,

            'stage' =>
                ErpSyncFailureStage::class,

            'page' =>
                'integer',

            'retryable' =>
                'boolean',

            'safe_context' =>
                'array',

            'occurred_at' =>
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

    public function runResource(): BelongsTo
    {
        return $this->belongsTo(
            ErpSyncRunResource::class,
            'erp_sync_run_resource_id'
        );
    }
}