<?php

namespace App\DTOs\Dashboard;

use App\DTOs\Analytics\MaintenanceKpiSummary;
use JsonSerializable;

final readonly class MaintenanceDashboardSnapshot implements JsonSerializable
{
    public function __construct(
        public bool $hasData,
        public int $downtimeEventCount,
        public int $totalDowntimeMinutes,
        public int $maintenanceInterventionCount,
        public int $faultEventCount,
        public int $repeatedFailureMachineCount,
        public ?float $availabilityPercentage,
        public ?float $mttrMinutes,
        public ?float $mtbfMinutes,
    ) {
    }

    public static function fromSummary(
        MaintenanceKpiSummary $summary
    ): self {
        return new self(
            hasData: ! $summary->isEmpty(),
            downtimeEventCount:
                $summary->downtimeEventCount,
            totalDowntimeMinutes:
                $summary->totalDowntimeMinutes,
            maintenanceInterventionCount:
                $summary->maintenanceInterventionCount,
            faultEventCount:
                $summary->faultEventCount,
            repeatedFailureMachineCount:
                $summary->repeatedFailureMachineCount,
            availabilityPercentage:
                $summary->availabilityPercentage,
            mttrMinutes:
                $summary->mttrMinutes,
            mtbfMinutes:
                $summary->mtbfMinutes,
        );
    }

    /**
     * @return array<string, bool|float|int|null>
     */
    public function toArray(): array
    {
        return [
            'has_data' => $this->hasData,
            'downtime_event_count' =>
                $this->downtimeEventCount,
            'total_downtime_minutes' =>
                $this->totalDowntimeMinutes,
            'maintenance_intervention_count' =>
                $this->maintenanceInterventionCount,
            'fault_event_count' =>
                $this->faultEventCount,
            'repeated_failure_machine_count' =>
                $this->repeatedFailureMachineCount,
            'availability_percentage' =>
                $this->availabilityPercentage,
            'mttr_minutes' => $this->mttrMinutes,
            'mtbf_minutes' => $this->mtbfMinutes,
        ];
    }

    /**
     * @return array<string, bool|float|int|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
