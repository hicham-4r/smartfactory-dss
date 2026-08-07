<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpOperatorAssignment extends Model
{
    use HasFactory;

    protected $table = 'erp_operator_assignments';

    protected $fillable = [
        'operator_id',
        'production_line_id',
        'shift_id',
        'role_on_line',
        'assigned_from',
        'assigned_until',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'date',
            'assigned_until' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(
            ErpOperator::class,
            'operator_id'
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
}