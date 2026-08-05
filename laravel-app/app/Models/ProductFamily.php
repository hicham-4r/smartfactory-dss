<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductFamily extends Model
{
    use HasSourceMetadata;

    /**
     * Source synchronization fields are intentionally excluded.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    /**
     * Products belonging to this family.
     */
    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class
        );
    }

    /**
     * Active products belonging to this family.
     */
    public function activeProducts(): HasMany
    {
        return $this
            ->products()
            ->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->sourceMetadataCasts(),
            'is_active' => 'boolean',
        ];
    }
}