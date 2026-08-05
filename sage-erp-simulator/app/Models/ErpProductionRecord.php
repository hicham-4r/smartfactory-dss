<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpProductionRecord extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_production_records';

    protected $fillable = [
        'production_batch_id',
        'machine_id',
        'process_stage_id',
        'record_number',
        'interval_start_at',
        'interval_end_at',
        'recorded_at',
        'target_quantity',
        'gross_quantity',
        'good_quantity',
        'rejected_quantity',
        'runtime_minutes',
        'downtime_minutes',
        'quality_rate_percent',
        'is_late_arrival',
        'source_updated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'interval_start_at' => 'datetime',
            'interval_end_at' => 'datetime',
            'recorded_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'target_quantity' => 'integer',
            'gross_quantity' => 'integer',
            'good_quantity' => 'integer',
            'rejected_quantity' => 'integer',
            'runtime_minutes' => 'integer',
            'downtime_minutes' => 'integer',
            'quality_rate_percent' => 'decimal:2',
            'is_late_arrival' => 'boolean',
        ];
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionBatch::class,
            'production_batch_id'
        );
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(
            ErpMachine::class,
            'machine_id'
        );
    }

    public function processStage(): BelongsTo
    {
        return $this->belongsTo(
            ErpProcessStage::class,
            'process_stage_id'
        );
    }
}