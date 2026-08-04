<?php

namespace App\DTOs\Dashboard;

use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class DashboardOverview implements JsonSerializable
{
    /** @param list<DashboardModuleCard> $modules */
    public function __construct(
        public DashboardFilter $filter,
        public CarbonImmutable $generatedAt,
        public ?RoleName $primaryRole,
        public array $modules,
        public ?OperatorDashboardSnapshot $operatorDashboard,
        public ?ProductionDashboardSnapshot $production,
        public ?MaintenanceDashboardSnapshot $maintenance,
        public ?QualityDashboardSnapshot $quality,
        public ?ProductionSupervisorDashboardSnapshot $productionSupervisor = null,
        public ?ProductionManagerDashboardSnapshot $productionManager = null,
        public ?MaintenanceManagerDashboardSnapshot $maintenanceManager = null,
    ) {
    }

    public function roleLabel(): string
    {
        return $this->primaryRole?->label()
            ?? 'Authorized user';
    }

    public function hasAnySnapshot(): bool
    {
        return $this->operatorDashboard !== null
            || $this->production !== null
            || $this->maintenance !== null
            || $this->quality !== null
            || $this->productionSupervisor !== null
            || $this->productionManager !== null
            || $this->maintenanceManager !== null;
    }

    public function dataBasisLabel(): string
    {
        return 'This shared overview reuses the validated production, maintenance and quality KPI services for the selected period. '
            .'Quantities with different units are not combined. '
            .'All displayed operational records are simulated ERP or DSS prototype data.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'generated_at' => $this->generatedAt
                ->utc()
                ->toIso8601String(),
            'primary_role' => $this->primaryRole?->value,
            'role_label' => $this->roleLabel(),
            'data_basis' => $this->dataBasisLabel(),
            'modules' => array_map(
                static fn (
                    DashboardModuleCard $card
                ): array => $card->toArray(),
                $this->modules
            ),
            'operator_dashboard' =>
                $this->operatorDashboard?->toArray(),
            'production' => $this->production?->toArray(),
            'maintenance' => $this->maintenance?->toArray(),
            'quality' => $this->quality?->toArray(),
            'production_supervisor' =>
                $this->productionSupervisor?->toArray(),
            'production_manager' =>
                $this->productionManager?->toArray(),
            'maintenance_manager' =>
                $this->maintenanceManager?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
