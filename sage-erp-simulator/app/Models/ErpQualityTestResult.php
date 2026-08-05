<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpQualityTestResult extends Model
{
    use HasFactory;

    protected $table = 'erp_quality_test_results';

    protected $fillable = [
        'quality_inspection_id',
        'test_code',
        'test_name',
        'test_category',
        'numeric_value',
        'text_value',
        'unit',
        'minimum_specification',
        'maximum_specification',
        'result',
        'tested_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:4',
            'minimum_specification' => 'decimal:4',
            'maximum_specification' => 'decimal:4',
            'tested_at' => 'datetime',
        ];
    }

    public function qualityInspection(): BelongsTo
    {
        return $this->belongsTo(
            ErpQualityInspection::class,
            'quality_inspection_id'
        );
    }
}