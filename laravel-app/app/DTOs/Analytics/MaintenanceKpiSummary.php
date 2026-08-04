<?php

namespace App\DTOs\Analytics;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class MaintenanceKpiSummary implements
    JsonSerializable
{
    /**
     * @param list<MaintenanceMachineMetric> $machines
     * @param list<MaintenanceTypeMetric> $maintenanceTypes
     */
    public function __construct(
        public MaintenanceAnalyticsFilter $filter,
        public CarbonImmutable $generatedAt,
        public array $machines,
        public array $maintenanceTypes,
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
        public int $repeatedFailureMachineCount,
        public ?float $availabilityPercentage,
        public ?float $mttrMinutes,
        public ?float $mtbfMinutes,
        public ?float $failuresPer100RunningHours,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->machines === []
            && $this->maintenanceTypes === [];
    }

    public function highestDowntimeMachine(): ?MaintenanceMachineMetric
    {
        foreach ($this->machines as $machine) {
            if ($machine->totalDowntimeMinutes > 0) {
                return $machine;
            }
        }

        return null;
    }

    public function hasUnclassifiedDowntime(): bool
    {
        return $this->unclassifiedDowntimeMinutes > 0;
    }

    public function hasObservedStatusCoverage(): bool
    {
        return $this->observedStatusMinutes > 0;
    }

    public function dataBasisLabel(): string
    {
        return 'Downtime is attributed to the event start date. '
            .'Planned/unplanned classification uses the dedicated ERP category when present and a deterministic source-type/title fallback when it is missing. '
            .'Availability is observed machine-state availability; missing state end times are reconstructed from the next state transition and clipped to the selected period. '
            .'MTTR uses completed corrective-maintenance downtime. '
            .'MTBF uses observed running minutes divided by recognized failure downtime events, with fault-state transitions used only as a fallback.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter' =>
                $this->filter->toArray(),

            'generated_at' =>
                $this->generatedAt
                    ->utc()
                    ->toIso8601String(),

            'data_basis' =>
                $this->dataBasisLabel(),

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

            'repeated_failure_machine_count' =>
                $this->repeatedFailureMachineCount,

            'availability_percentage' =>
                $this->availabilityPercentage,

            'mttr_minutes' =>
                $this->mttrMinutes,

            'mtbf_minutes' =>
                $this->mtbfMinutes,

            'failures_per_100_running_hours' =>
                $this->failuresPer100RunningHours,

            'machines' =>
                array_map(
                    static fn (
                        MaintenanceMachineMetric $machine
                    ): array => $machine->toArray(),
                    $this->machines
                ),

            'maintenance_types' =>
                array_map(
                    static fn (
                        MaintenanceTypeMetric $type
                    ): array => $type->toArray(),
                    $this->maintenanceTypes
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
