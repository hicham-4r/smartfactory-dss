<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorAssignment extends Model
{
    use HasSourceMetadata;

    /**
     * assigned_by is excluded because it must come from the
     * authenticated administrator or synchronization service.
     *
     * @var list<string>
     */
    protected $fillable = [
        'operator_id',
        'production_line_id',
        'shift_id',
        'starts_on',
        'ends_on',
        'is_primary',
        'is_active',
    ];

    /**
     * Assigned operator.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(
            Operator::class
        );
    }

    /**
     * Assigned production line.
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ProductionLine::class
        );
    }

    /**
     * Assigned production shift.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(
            Shift::class
        );
    }

    /**
     * User who manually created the assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_by'
        );
    }

    /**
     * Restrict a query to assignments effective on a given date.
     */
    public function scopeCurrent(
        Builder $query,
        ?DateTimeInterface $date = null
    ): Builder {
        $effectiveDate = $date === null
            ? CarbonImmutable::today()
            : CarbonImmutable::instance($date)
                ->startOfDay();

        return $query
            ->where(
                $this->qualifyColumn('is_active'),
                true
            )
            ->whereDate(
                $this->qualifyColumn('starts_on'),
                '<=',
                $effectiveDate->toDateString()
            )
            ->where(
                function (Builder $query) use (
                    $effectiveDate
                ): void {
                    $query
                        ->whereNull(
                            $this->qualifyColumn(
                                'ends_on'
                            )
                        )
                        ->orWhereDate(
                            $this->qualifyColumn(
                                'ends_on'
                            ),
                            '>=',
                            $effectiveDate
                                ->toDateString()
                        );
                }
            );
    }

    /**
     * Restrict a query to primary assignments.
     */
    public function scopePrimary(
        Builder $query
    ): Builder {
        return $query->where(
            $this->qualifyColumn('is_primary'),
            true
        );
    }

    /**
     * Determine whether this assignment applies on a date.
     */
    public function isEffectiveOn(
        DateTimeInterface $date
    ): bool {
        if (! $this->is_active) {
            return false;
        }

        $effectiveDate = CarbonImmutable::instance(
            $date
        )->startOfDay();

        $startsOn = CarbonImmutable::instance(
            $this->starts_on
        )->startOfDay();

        $endsOn = $this->ends_on === null
            ? null
            : CarbonImmutable::instance(
                $this->ends_on
            )->startOfDay();

        return $startsOn->lessThanOrEqualTo(
            $effectiveDate
        )
            && (
                $endsOn === null
                || $endsOn->greaterThanOrEqualTo(
                    $effectiveDate
                )
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->sourceMetadataCasts(),
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}