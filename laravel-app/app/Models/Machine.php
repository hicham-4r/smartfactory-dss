<?php

namespace App\Models;

use App\Models\Concerns\HasSourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Machine extends Model
{
    use HasSourceMetadata;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'production_line_id',
        'code',
        'name',
        'machine_type',
        'manufacturer',
        'model',
        'serial_number',
        'sequence_number',
        'is_critical',
        'is_active',
    ];

    /**
     * Production line containing this machine.
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ProductionLine::class
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->sourceMetadataCasts(),
            'sequence_number' => 'integer',
            'is_critical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}