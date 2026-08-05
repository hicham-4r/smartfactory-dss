<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpMachine extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_machines';

    protected $fillable = [
        'code',
        'name',
        'machine_type',
        'manufacturer',
        'model_reference',
        'serial_number',
        'status',
        'criticality',
        'installation_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Production lines to which this machine is assigned.
     */
    public function productionLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ErpProductionLine::class,
            'erp_line_machines',
            'machine_id',
            'production_line_id'
        )
            ->withPivot([
                'process_stage_id',
                'sequence_order',
                'station_code',
                'is_primary',
                'assigned_at',
            ])
            ->withTimestamps();
    }

    /**
     * Downtime events associated with this machine.
     */
    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(
            ErpDowntimeEvent::class,
            'machine_id'
        );
    }

    /**
     * Status history associated with this machine.
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(
            ErpMachineStatusEvent::class,
            'machine_id'
        );
    }

    /**
     * Preventive and corrective maintenance history.
     */
    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(
            ErpMaintenanceHistory::class,
            'machine_id'
        );
    }
}