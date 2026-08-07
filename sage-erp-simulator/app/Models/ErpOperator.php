<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpOperator extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_operators';

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'hire_date',
        'status',
        'is_active',
    ];

    protected $appends = [
        'full_name',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(
            ErpOperatorAssignment::class,
            'operator_id'
        );
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            $this->first_name . ' ' . $this->last_name
        );
    }
}