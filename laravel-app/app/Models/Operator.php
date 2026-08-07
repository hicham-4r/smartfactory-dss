<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operator extends Model
{
    use HasSourceMetadata;

    /**
     * user_id is excluded because account linkage must be controlled
     * by an authorized service.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'hired_on',
        'is_active',
    ];

    /**
     * Optional DSS authentication account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /**
     * Production-line and shift assignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(
            OperatorAssignment::class
        );
    }

    /**
     * Current active assignments.
     */
    public function currentAssignments(): HasMany
    {
        return $this
            ->assignments()
            ->current();
    }

    /**
     * Full operator name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(
                $this->first_name
                .' '
                .$this->last_name
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
            'hired_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}