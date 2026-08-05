<?php

namespace App\Models;

use App\Models\Concerns\HasExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProductionLine extends Model
{
    use HasFactory;
    use HasExternalId;

    protected $table = 'erp_production_lines';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'nominal_capacity_units_per_hour',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nominal_capacity_units_per_hour' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Machines assigned to this production line.
     */
    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(
            ErpMachine::class,
            'erp_line_machines',
            'production_line_id',
            'machine_id'
        )
            ->withPivot([
                'process_stage_id',
                'sequence_order',
                'station_code',
                'is_primary',
                'assigned_at',
            ])
            ->withTimestamps()
            ->orderByPivot('sequence_order');
    }

    /**
     * Operator assignments for this production line.
     */
    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(
            ErpOperatorAssignment::class,
            'production_line_id'
        );
    }

    /**
     * Products that can be manufactured on this line.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            ErpProduct::class,
            'erp_product_lines',
            'production_line_id',
            'product_id'
        )
            ->withPivot([
                'is_preferred',
                'nominal_rate_units_per_hour',
            ])
            ->withTimestamps();
    }

    /**
     * Downtime events that affected this production line.
     */
    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(
            ErpDowntimeEvent::class,
            'production_line_id'
        );
    }

    /**
     * Machine status events recorded for this production line.
     */
    public function machineStatusEvents(): HasMany
    {
        return $this->hasMany(
            ErpMachineStatusEvent::class,
            'production_line_id'
        );
    }

    /**
     * Maintenance interventions performed on this line's machines.
     */
    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(
            ErpMaintenanceHistory::class,
            'production_line_id'
        );
    }
}