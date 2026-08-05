<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpProductRouteStep extends Model
{
    use HasFactory;

    protected $table = 'erp_product_route_steps';

    protected $fillable = [
        'product_route_id',
        'process_stage_id',
        'sequence_order',
        'is_required',
        'target_duration_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sequence_order' => 'integer',
            'is_required' => 'boolean',
            'target_duration_minutes' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductRoute::class,
            'product_route_id'
        );
    }

    public function processStage(): BelongsTo
    {
        return $this->belongsTo(
            ErpProcessStage::class,
            'process_stage_id'
        );
    }
}