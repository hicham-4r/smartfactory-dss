<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpMachineStatusEvent extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_machine_status_events';

    protected $fillable = [
        'machine_id',
        'production_line_id',
        'shift_id',
        'status_event_number',
        'status_code',
        'started_at',
        'ended_at',
        'duration_minutes',
        'is_late_arrival',
        'source_updated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'duration_minutes' => 'integer',
            'is_late_arrival' => 'boolean',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(
            ErpMachine::class,
            'machine_id'
        );
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(
            ErpProductionLine::class,
            'production_line_id'
        );
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(
            ErpShift::class,
            'shift_id'
        );
    }
}