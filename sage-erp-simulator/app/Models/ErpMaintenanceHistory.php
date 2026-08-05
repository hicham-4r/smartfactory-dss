<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpMaintenanceHistory extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_maintenance_history';

    protected $fillable = [
        'machine_id',
        'production_line_id',
        'downtime_event_id',
        'maintenance_number',
        'maintenance_type',
        'priority',
        'status',
        'reported_at',
        'started_at',
        'completed_at',
        'repair_duration_minutes',
        'failure_code',
        'failure_description',
        'root_cause',
        'actions_taken',
        'technician_name',
        'parts_cost',
        'labor_cost',
        'total_cost',
        'currency_code',
        'is_late_arrival',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'repair_duration_minutes' => 'integer',
            'parts_cost' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
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

    public function downtimeEvent(): BelongsTo
    {
        return $this->belongsTo(
            ErpDowntimeEvent::class,
            'downtime_event_id'
        );
    }
}