<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProductionOrder extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_production_orders';

    protected $fillable = [
        'product_id',
        'production_line_id',
        'order_number',
        'planned_start_at',
        'planned_end_at',
        'planned_quantity',
        'priority',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'planned_quantity' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            ErpProduct::class,
            'product_id'
        );
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionLine::class,
            'production_line_id'
        );
    }

    public function batches(): HasMany
    {
        return $this->hasMany(
            ErpProductionBatch::class,
            'production_order_id'
        )->orderBy('scheduled_start_at');
    }
}