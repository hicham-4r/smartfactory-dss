<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpFinishedLotRelease extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_finished_lot_releases';

    protected $fillable = [
        'production_batch_id',
        'quality_inspection_id',
        'release_number',
        'lot_number',
        'decision',
        'warehouse_status',
        'decision_at',
        'released_at',
        'released_by',
        'quality_certificate_number',
        'approved_quantity',
        'blocked_quantity',
        'rejected_quantity',
        'expiry_date',
        'decision_reason',
        'is_late_arrival',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_at' => 'datetime',
            'released_at' => 'datetime',
            'approved_quantity' => 'integer',
            'blocked_quantity' => 'integer',
            'rejected_quantity' => 'integer',
            'expiry_date' => 'date',
            'is_late_arrival' => 'boolean',
            'source_updated_at' => 'datetime',
        ];
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionBatch::class,
            'production_batch_id'
        );
    }

    public function qualityInspection(): BelongsTo
    {
        return $this->belongsTo(
            ErpQualityInspection::class,
            'quality_inspection_id'
        );
    }
}