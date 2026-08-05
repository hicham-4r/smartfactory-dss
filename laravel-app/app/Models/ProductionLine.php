<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionLine extends Model
{
    use HasSourceMetadata;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'plant_area',
        'description',
        'nominal_capacity_per_hour',
        'capacity_unit',
        'is_active',
    ];

    /**
     * Machines installed on this production line.
     */
    public function machines(): HasMany
    {
        return $this->hasMany(
            Machine::class
        );
    }

    /**
     * Active machines installed on this line.
     */
    public function activeMachines(): HasMany
    {
        return $this
            ->machines()
            ->where('is_active', true)
            ->orderBy('sequence_number');
    }

    /**
     * Operator assignments associated with this line.
     */
    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(
            OperatorAssignment::class
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->sourceMetadataCasts(),

            'nominal_capacity_per_hour' =>
                'decimal:3',

            'is_active' => 'boolean',
        ];
    }
}