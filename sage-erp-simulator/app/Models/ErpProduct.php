<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProduct extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_products';

    protected $fillable = [
        'product_family_id',
        'packaging_format_id',
        'code',
        'name',
        'flavor',
        'beverage_type',
        'contains_milk',
        'shelf_life_days',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'contains_milk' => 'boolean',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductFamily::class,
            'product_family_id'
        );
    }

    public function packagingFormat(): BelongsTo
    {
        return $this->belongsTo(
            ErpPackagingFormat::class,
            'packaging_format_id'
        );
    }

    public function productionLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ErpProductionLine::class,
            'erp_product_lines',
            'product_id',
            'production_line_id'
        )
            ->withPivot([
                'is_preferred',
                'nominal_rate_units_per_hour',
            ])
            ->withTimestamps();
    }

    public function routes(): HasMany
    {
        return $this->hasMany(
            ErpProductRoute::class,
            'product_id'
        );
    }
}