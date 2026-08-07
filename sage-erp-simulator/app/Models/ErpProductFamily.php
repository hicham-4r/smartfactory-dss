<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProductFamily extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_product_families';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Products belonging to this product family.
     */
    public function products(): HasMany
    {
        return $this->hasMany(
            ErpProduct::class,
            'product_family_id'
        );
    }
}