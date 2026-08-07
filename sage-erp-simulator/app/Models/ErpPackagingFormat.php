<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpPackagingFormat extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_packaging_formats';

    protected $fillable = [
        'code',
        'label',
        'volume_ml',
        'package_type',
        'closure_type',
        'has_straw',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'volume_ml' => 'integer',
            'has_straw' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(
            ErpProduct::class,
            'packaging_format_id'
        );
    }
}