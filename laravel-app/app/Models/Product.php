<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasSourceMetadata;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_family_id',
        'code',
        'sku',
        'name',
        'base_unit',
        'package_format',
        'nominal_volume',
        'is_active',
    ];

    /**
     * Product family owning this product.
     */
    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(
            ProductFamily::class
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->sourceMetadataCasts(),
            'nominal_volume' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}