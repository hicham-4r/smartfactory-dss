<?php

namespace App\DTOs\Analytics;

use JsonSerializable;

final readonly class MaintenanceMachineMetric implements
    JsonSerializable
{
    public function __construct(
        public int $machineId,
        public string $machineCode,
        public string $machineName,
        public int $productionLineId,
        public string $productionLineName,
        public int $downtimeEventCount,
        public int $openDowntimeEventCount,
        public int $totalDowntimeMinutes,
        public int $plannedDowntimeMinutes,
        public int $unplannedDowntimeMinutes,
        public int $unclassifiedDowntimeMinutes,
        public int $observedStatusMinutes,
        public int $runningMinutes,
        public int $faultEventCount,
        public int $maintenanceInterventionCount,
        public int $preventiveInterventionCount,
        public int $correctiveInterventionCount,
        public int $completedCorrectiveCount,
        public int $correctiveRepairMinutes,
        public ?float $availabilityPercentage,
        public ?float $mttrMinutes,
        public ?float $mtbfMinutes,
        public ?float $failuresPer100RunningHours,
    ) {
    }

    public function hasRepeatedFailures(): bool
    {
        return $this->faultEventCount >= 2;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'machine_id' =>
                $this->machineId,

            'machine_code' =>
                $this->machineCode,

            'machine_name' =>
                $this->machineName,

            'production_line_id' =>
                $this->productionLineId,

            'production_line_name' =>
                $this->productionLineName,

            'downtime_event_count' =>
                $this->downtimeEventCount,

            'open_downtime_event_count' =>
                $this->openDowntimeEventCount,

            'total_downtime_minutes' =>
                $this->totalDowntimeMinutes,

            'planned_downtime_minutes' =>
                $this->plannedDowntimeMinutes,

            'unplanned_downtime_minutes' =>
                $this->unplannedDowntimeMinutes,

            'unclassified_downtime_minutes' =>
                $this->unclassifiedDowntimeMinutes,

            'observed_status_minutes' =>
                $this->observedStatusMinutes,

            'running_minutes' =>
                $this->runningMinutes,

            'fault_event_count' =>
                $this->faultEventCount,

            'maintenance_intervention_count' =>
                $this->maintenanceInterventionCount,

            'preventive_intervention_count' =>
                $this->preventiveInterventionCount,

            'corrective_intervention_count' =>
                $this->correctiveInterventionCount,

            'completed_corrective_count' =>
                $this->completedCorrectiveCount,

            'corrective_repair_minutes' =>
                $this->correctiveRepairMinutes,

            'availability_percentage' =>
                $this->availabilityPercentage,

            'mttr_minutes' =>
                $this->mttrMinutes,

            'mtbf_minutes' =>
                $this->mtbfMinutes,

            'failures_per_100_running_hours' =>
                $this->failuresPer100RunningHours,

            'has_repeated_failures' =>
                $this->hasRepeatedFailures(),
        ];
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
