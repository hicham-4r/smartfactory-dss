<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpShift extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_shifts';

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'crosses_midnight',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(
            ErpOperatorAssignment::class,
            'shift_id'
        );
    }
}