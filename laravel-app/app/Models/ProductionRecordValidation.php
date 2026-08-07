<?php

namespace App\Models;

use App\Enums\Production\ProductionValidationDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecordValidation extends Model
{
    /**
     * Validation decisions must be created through the workflow service.
     *
     * @var list<string>
     */
    protected $fillable = [];

    public function productionRecord(): BelongsTo
    {
        return $this->belongsTo(
            ProductionRecord::class
        );
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'decided_by'
        );
    }

    public function scopeDecision(
        Builder $query,
        ProductionValidationDecision|string $decision
    ): Builder {
        $value =
            $decision instanceof ProductionValidationDecision
                ? $decision->value
                : ProductionValidationDecision::from(
                    $decision
                )->value;

        return $query->where(
            $this->qualifyColumn('decision'),
            $value
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' =>
                ProductionValidationDecision::class,

            'record_version' => 'integer',

            'decided_at' =>
                'immutable_datetime',
        ];
    }
}