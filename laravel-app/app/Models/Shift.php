<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasSourceMetadata;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'starts_at',
        'ends_at',
        'crosses_midnight',
        'is_active',
    ];

    /**
     * Operator assignments using this shift.
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
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}