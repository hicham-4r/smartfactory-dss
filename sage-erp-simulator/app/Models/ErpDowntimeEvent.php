<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ErpDowntimeEvent extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_downtime_events';

    protected $fillable = [
        'machine_id',
        'production_line_id',
        'production_batch_id',
        'shift_id',
        'event_number',
        'category',
        'downtime_type',
        'reason_code',
        'reason_description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'production_impact_units',
        'status',
        'is_late_arrival',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'duration_minutes' => 'integer',
            'production_impact_units' => 'integer',
            'is_late_arrival' => 'boolean',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(
            ErpMachine::class,
            'machine_id'
        );
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionLine::class,
            'production_line_id'
        );
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionBatch::class,
            'production_batch_id'
        );
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(
            ErpShift::class,
            'shift_id'
        );
    }

    public function maintenanceRecord(): HasOne
    {
        return $this->hasOne(
            ErpMaintenanceHistory::class,
            'downtime_event_id'
        );
    }
}