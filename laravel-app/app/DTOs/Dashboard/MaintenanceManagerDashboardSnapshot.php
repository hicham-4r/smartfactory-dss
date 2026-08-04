<?php

namespace App\DTOs\Dashboard;

use App\DTOs\Analytics\MaintenanceKpiSummary;
use App\DTOs\Analytics\MaintenanceMachineMetric;
use JsonSerializable;

final readonly class MaintenanceManagerDashboardSnapshot implements
    JsonSerializable
{
    /**
     * @param list<DashboardFilterOption> $productionLines
     * @param list<MaintenanceManagerMachineOption> $machines
     */
    public function __construct(
        public DashboardFilter $filter,
        public MaintenanceKpiSummary $maintenance,
        public array $productionLines,
        public array $machines,
    ) {
    }

    public function needsAttention(): bool
    {
        return $this->maintenance
                ->openDowntimeEventCount > 0
            || $this->maintenance
                ->repeatedFailureMachineCount > 0
            || $this->maintenance
                ->unclassifiedDowntimeMinutes > 0;
    }

    public function highestDowntimeMachine(): ?MaintenanceMachineMetric
    {
        return $this->maintenance
            ->highestDowntimeMachine();
    }

    public function dataBasisLabel(): string
    {
        return 'The maintenance manager dashboard reuses the verified maintenance KPI service. '
            .'Line and machine filters contain only period-backed maintenance data. '
            .'Availability is observed machine-state availability, not OEE. '
            .'MTTR uses completed corrective interventions and MTBF uses observed running time divided by recognized failures. '
            .'No AI recommendation is generated in this deterministic step.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'data_basis' => $this->dataBasisLabel(),
            'needs_attention' => $this->needsAttention(),
            'maintenance' =>
                $this->maintenance->toArray(),
            'production_lines' => array_map(
                static fn (
                    DashboardFilterOption $option
                ): array => $option->toArray(),
                $this->productionLines
            ),
            'machines' => array_map(
                static fn (
                    MaintenanceManagerMachineOption $option
                ): array => $option->toArray(),
                $this->machines
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
