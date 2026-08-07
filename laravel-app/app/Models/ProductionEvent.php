<?php

namespace App\Models;

use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Models\Concerns\HasOptimisticLocking;
use App\Models\Concerns\HasProductionExecutionMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEvent extends Model
{
    use HasProductionExecutionMetadata;
    use HasOptimisticLocking;

    /**
     * Resolution and actor fields are service-controlled.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_number',
        'production_batch_id',
        'production_record_id',
        'production_line_id',
        'machine_id',
        'shift_id',
        'operator_id',
        'event_type',
        'severity',
        'title',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(
            ProductionBatch::class
        );
    }

    public function productionRecord(): BelongsTo
    {
        return $this->belongsTo(
            ProductionRecord::class
        );
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ProductionLine::class
        );
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(
            Machine::class
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_by'
        );
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by'
        );
    }

    public function scopeType(
        Builder $query,
        ProductionEventType|string $type
    ): Builder {
        $value = $type instanceof ProductionEventType
            ? $type->value
            : ProductionEventType::from($type)->value;

        return $query->where(
            $this->qualifyColumn('event_type'),
            $value
        );
    }

    public function scopeSeverity(
        Builder $query,
        ProductionEventSeverity|string $severity
    ): Builder {
        $value =
            $severity instanceof ProductionEventSeverity
                ? $severity->value
                : ProductionEventSeverity::from(
                    $severity
                )->value;

        return $query->where(
            $this->qualifyColumn('severity'),
            $value
        );
    }

    public function scopeUnresolved(
        Builder $query
    ): Builder {
        return $query->where(
            $this->qualifyColumn('is_resolved'),
            false
        );
    }

    public function canBeResolved(): bool
    {
        return ! $this->is_resolved;
    }

    public function calculatedDurationMinutes(): ?int
    {
        if (
            $this->started_at === null
            || $this->ended_at === null
        ) {
            return null;
        }

        return $this->started_at->diffInMinutes(
            $this->ended_at
        );
    }

    public function storedDurationMatchesTimeline(): bool
    {
        $calculated =
            $this->calculatedDurationMinutes();

        return $calculated === null
            || $this->duration_minutes === null
            || $calculated === $this->duration_minutes;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->productionExecutionMetadataCasts(),

            'event_type' =>
                ProductionEventType::class,

            'severity' =>
                ProductionEventSeverity::class,

            'started_at' =>
                'immutable_datetime',

            'ended_at' =>
                'immutable_datetime',

            'resolved_at' =>
                'immutable_datetime',

            'duration_minutes' => 'integer',
            'is_resolved' => 'boolean',
        ];
    }
}