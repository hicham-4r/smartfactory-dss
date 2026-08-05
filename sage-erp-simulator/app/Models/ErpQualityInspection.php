<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ErpQualityInspection extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_quality_inspections';

    protected $fillable = [
        'production_batch_id',
        'product_id',
        'production_line_id',
        'shift_id',
        'inspection_number',
        'inspection_type',
        'sampled_at',
        'inspection_started_at',
        'inspection_completed_at',
        'inspector_name',
        'status',
        'result',
        'overall_score_percent',
        'nonconformity_code',
        'nonconformity_description',
        'corrective_action',
        'is_late_arrival',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'inspection_started_at' => 'datetime',
            'inspection_completed_at' => 'datetime',
            'overall_score_percent' => 'decimal:2',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            ErpProduct::class,
            'product_id'
        );
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionLine::class,
            'production_line_id'
        );
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(
            ErpShift::class,
            'shift_id'
        );
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(
            ErpQualityTestResult::class,
            'quality_inspection_id'
        )->orderBy('id');
    }

    public function lotRelease(): HasOne
    {
        return $this->hasOne(
            ErpFinishedLotRelease::class,
            'quality_inspection_id'
        );
    }
}