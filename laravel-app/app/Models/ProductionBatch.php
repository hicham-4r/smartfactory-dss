<?php

namespace App\Models;

use App\Enums\Production\ProductionBatchStatus;
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Models\Concerns\HasOptimisticLocking;
use App\Models\Concerns\HasProductionExecutionMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use HasProductionExecutionMetadata;
    use HasOptimisticLocking;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'production_order_id',
        'batch_number',
        'sequence_number',
        'planned_quantity',
        'actual_good_quantity',
        'actual_rejected_quantity',
        'quantity_unit',
        'scheduled_start_at',
        'actual_start_at',
        'actual_end_at',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(
            ProductionOrder::class
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

    public function records(): HasMany
    {
        return $this->hasMany(
            ProductionRecord::class
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            ProductionEvent::class
        );
    }

    public function scopeStatus(
        Builder $query,
        ProductionBatchStatus|string $status
    ): Builder {
        $value = $status instanceof ProductionBatchStatus
            ? $status->value
            : ProductionBatchStatus::from($status)->value;

        return $query->where(
            $this->qualifyColumn('status'),
            $value
        );
    }

    public function canTransitionTo(
        ProductionBatchStatus $target
    ): bool {
        return $this->status->canTransitionTo(
            $target
        );
    }

    public function assertCanTransitionTo(
        ProductionBatchStatus $target
    ): void {
        if (! $this->canTransitionTo($target)) {
            throw InvalidProductionStatusTransition::between(
                'production batch',
                $this->status,
                $target
            );
        }
    }

    public function actualTotalQuantity(): string
    {
        $total = $this->quantityToMilliUnits(
            $this->actual_good_quantity
        ) + $this->quantityToMilliUnits(
            $this->actual_rejected_quantity
        );

        return number_format(
            $total / 1000,
            3,
            '.',
            ''
        );
    }

    public function remainingPlannedQuantity(): string
    {
        $remaining = $this->quantityToMilliUnits(
            $this->planned_quantity
        ) - $this->quantityToMilliUnits(
            $this->actualTotalQuantity()
        );

        return number_format(
            max(0, $remaining) / 1000,
            3,
            '.',
            ''
        );
    }

    public function hasNonNegativeQuantities(): bool
    {
        return (float) $this->planned_quantity >= 0
            && (float) $this->actual_good_quantity >= 0
            && (float) $this->actual_rejected_quantity >= 0;
    }

    private function quantityToMilliUnits(
        string|int|float|null $quantity
    ): int {
        return (int) round(
            ((float) $quantity) * 1000
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->productionExecutionMetadataCasts(),

            'status' => ProductionBatchStatus::class,
            'sequence_number' => 'integer',

            'planned_quantity' => 'decimal:3',

            'actual_good_quantity' =>
                'decimal:3',

            'actual_rejected_quantity' =>
                'decimal:3',

            'scheduled_start_at' =>
                'immutable_datetime',

            'actual_start_at' =>
                'immutable_datetime',

            'actual_end_at' =>
                'immutable_datetime',
        ];
    }
}