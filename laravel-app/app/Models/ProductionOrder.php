<?php

namespace App\Models;

use App\Enums\Production\ProductionOrderStatus;
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Models\Concerns\HasOptimisticLocking;
use App\Models\Concerns\HasProductionExecutionMetadata;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProductionOrder extends Model
{
    use HasProductionExecutionMetadata;
    use HasOptimisticLocking;

    /**
     * Actor, status, source and lock fields are service-controlled.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_number',
        'product_id',
        'production_line_id',
        'shift_id',
        'planned_start_at',
        'planned_end_at',
        'target_quantity',
        'quantity_unit',
        'priority',
        'instructions',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ProductionLine::class
        );
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(
            Shift::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function batches(): HasMany
    {
        return $this->hasMany(
            ProductionBatch::class
        );
    }

    public function events(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductionEvent::class,
            ProductionBatch::class,
            'production_order_id',
            'production_batch_id'
        );
    }

    public function scopeStatus(
        Builder $query,
        ProductionOrderStatus|string $status
    ): Builder {
        $value = $status instanceof ProductionOrderStatus
            ? $status->value
            : ProductionOrderStatus::from($status)->value;

        return $query->where(
            $this->qualifyColumn('status'),
            $value
        );
    }

    public function scopePlannedBetween(
        Builder $query,
        DateTimeInterface $from,
        DateTimeInterface $to
    ): Builder {
        return $query->whereBetween(
            $this->qualifyColumn('planned_start_at'),
            [
                $from,
                $to,
            ]
        );
    }

    public function canTransitionTo(
        ProductionOrderStatus $target
    ): bool {
        return $this->status->canTransitionTo(
            $target
        );
    }

    public function assertCanTransitionTo(
        ProductionOrderStatus $target
    ): void {
        if (! $this->canTransitionTo($target)) {
            throw InvalidProductionStatusTransition::between(
                'production order',
                $this->status,
                $target
            );
        }
    }

    public function hasValidPlannedWindow(): bool
    {
        return $this->planned_end_at === null
            || $this->planned_end_at
                ->greaterThan($this->planned_start_at);
    }

    public function hasPositiveTargetQuantity(): bool
    {
        return (float) $this->target_quantity > 0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->productionExecutionMetadataCasts(),

            'status' => ProductionOrderStatus::class,

            'planned_start_at' =>
                'immutable_datetime',

            'planned_end_at' =>
                'immutable_datetime',

            'target_quantity' => 'decimal:3',
            'priority' => 'integer',
        ];
    }
}