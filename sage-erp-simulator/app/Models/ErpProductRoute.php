<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProductRoute extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_product_routes';

    protected $fillable = [
        'product_id',
        'code',
        'name',
        'version',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            ErpProduct::class,
            'product_id'
        );
    }

    public function steps(): HasMany
    {
        return $this->hasMany(
            ErpProductRouteStep::class,
            'product_route_id'
        )->orderBy('sequence_order');
    }
}