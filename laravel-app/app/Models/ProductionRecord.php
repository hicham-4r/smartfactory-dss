<?php

namespace App\Models;

use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Models\Concerns\HasOptimisticLocking;
use App\Models\Concerns\HasProductionExecutionMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecord extends Model
{
    use HasProductionExecutionMetadata;
    use HasOptimisticLocking;

    /**
     * Workflow and accountability fields are service-controlled.
     *
     * @var list<string>
     */
    protected $fillable = [
        'record_number',
        'production_batch_id',
        'production_line_id',
        'shift_id',
        'operator_id',
        'production_date',
        'started_at',
        'ended_at',
        'produced_quantity',
        'good_quantity',
        'rejected_quantity',
        'quantity_unit',
        'runtime_minutes',
        'downtime_minutes',
        'notes',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(
            ProductionBatch::class
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

    public function operator(): BelongsTo
    {
        return $this->belongsTo(
            Operator::class
        );
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function validations(): HasMany
    {
        return $this->hasMany(
            ProductionRecordValidation::class
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
        ProductionRecordStatus|string $status
    ): Builder {
        $value = $status instanceof ProductionRecordStatus
            ? $status->value
            : ProductionRecordStatus::from($status)->value;

        return $query->where(
            $this->qualifyColumn('status'),
            $value
        );
    }

    public function scopeValidationStatus(
        Builder $query,
        ProductionValidationStatus|string $status
    ): Builder {
        $value = $status instanceof ProductionValidationStatus
            ? $status->value
            : ProductionValidationStatus::from($status)->value;

        return $query->where(
            $this->qualifyColumn('validation_status'),
            $value
        );
    }

    public function canTransitionTo(
        ProductionRecordStatus $target
    ): bool {
        return $this->status->canTransitionTo(
            $target
        );
    }

    public function assertCanTransitionTo(
        ProductionRecordStatus $target
    ): void {
        if (! $this->canTransitionTo($target)) {
            throw InvalidProductionStatusTransition::between(
                'production record',
                $this->status,
                $target
            );
        }
    }

    public function canTransitionValidationTo(
        ProductionValidationStatus $target
    ): bool {
        return $this->validation_status
            ->canTransitionTo($target);
    }

    public function assertCanTransitionValidationTo(
        ProductionValidationStatus $target
    ): void {
        if (! $this->canTransitionValidationTo($target)) {
            throw InvalidProductionStatusTransition::between(
                'production-record validation',
                $this->validation_status,
                $target
            );
        }
    }

    public function hasConsistentQuantityBreakdown(): bool
    {
        return $this->quantityToMilliUnits(
            $this->produced_quantity
        ) === (
            $this->quantityToMilliUnits(
                $this->good_quantity
            )
            + $this->quantityToMilliUnits(
                $this->rejected_quantity
            )
        );
    }

    public function hasNonNegativeOperationalValues(): bool
    {
        return (float) $this->produced_quantity >= 0
            && (float) $this->good_quantity >= 0
            && (float) $this->rejected_quantity >= 0
            && $this->runtime_minutes >= 0
            && $this->downtime_minutes >= 0;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === ProductionRecordStatus::Draft
            && $this->hasConsistentQuantityBreakdown()
            && $this->hasNonNegativeOperationalValues();
    }

    public function canBeValidated(): bool
    {
        return $this->status
            === ProductionRecordStatus::Submitted
            && $this->validation_status
                === ProductionValidationStatus::Pending;
    }

    public function isLocked(): bool
    {
        return $this->status === ProductionRecordStatus::Locked
            || $this->locked_at !== null;
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

            'status' =>
                ProductionRecordStatus::class,

            'validation_status' =>
                ProductionValidationStatus::class,

            'production_date' =>
                'immutable_date',

            'started_at' =>
                'immutable_datetime',

            'ended_at' =>
                'immutable_datetime',

            'submitted_at' =>
                'immutable_datetime',

            'locked_at' =>
                'immutable_datetime',

            'produced_quantity' => 'decimal:3',
            'good_quantity' => 'decimal:3',
            'rejected_quantity' => 'decimal:3',
            'runtime_minutes' => 'integer',
            'downtime_minutes' => 'integer',
        ];
    }
}