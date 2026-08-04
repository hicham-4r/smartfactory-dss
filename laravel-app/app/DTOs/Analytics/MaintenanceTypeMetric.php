<?php

namespace App\DTOs\Analytics;

use JsonSerializable;

final readonly class MaintenanceTypeMetric implements
    JsonSerializable
{
    public function __construct(
        public string $maintenanceType,
        public string $label,
        public int $interventionCount,
        public int $plannedCount,
        public int $inProgressCount,
        public int $completedCount,
        public int $cancelledCount,
        public int $downtimeMinutes,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'maintenance_type' =>
                $this->maintenanceType,

            'label' =>
                $this->label,

            'intervention_count' =>
                $this->interventionCount,

            'planned_count' =>
                $this->plannedCount,

            'in_progress_count' =>
                $this->inProgressCount,

            'completed_count' =>
                $this->completedCount,

            'cancelled_count' =>
                $this->cancelledCount,

            'downtime_minutes' =>
                $this->downtimeMinutes,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
