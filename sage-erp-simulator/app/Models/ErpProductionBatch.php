<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ErpProductionBatch extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_production_batches';

    protected $fillable = [
        'production_order_id',
        'shift_id',
        'operator_assignment_id',
        'batch_number',
        'lot_number',
        'scheduled_start_at',
        'scheduled_end_at',
        'actual_start_at',
        'actual_end_at',
        'planned_quantity',
        'gross_quantity',
        'good_quantity',
        'rejected_quantity',
        'status',
        'quality_status',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'expiry_date' => 'date',
            'planned_quantity' => 'integer',
            'gross_quantity' => 'integer',
            'good_quantity' => 'integer',
            'rejected_quantity' => 'integer',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionOrder::class,
            'production_order_id'
        );
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(
            ErpShift::class,
            'shift_id'
        );
    }

    public function operatorAssignment(): BelongsTo
    {
        return $this->belongsTo(
            ErpOperatorAssignment::class,
            'operator_assignment_id'
        );
    }

    public function records(): HasMany
    {
        return $this->hasMany(
            ErpProductionRecord::class,
            'production_batch_id'
        )->orderBy('interval_start_at');
    }

    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(
            ErpDowntimeEvent::class,
            'production_batch_id'
        );
    }

    public function qualityInspection(): HasOne
    {
        return $this->hasOne(
            ErpQualityInspection::class,
            'production_batch_id'
        );
    }

    public function finishedLotRelease(): HasOne
    {
        return $this->hasOne(
            ErpFinishedLotRelease::class,
            'production_batch_id'
        );
    }
}