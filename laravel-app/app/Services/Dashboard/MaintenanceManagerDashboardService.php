<?php

namespace App\Services\Dashboard;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\DTOs\Dashboard\DashboardFilter;
use App\DTOs\Dashboard\DashboardFilterOption;
use App\DTOs\Dashboard\MaintenanceManagerDashboardSnapshot;
use App\DTOs\Dashboard\MaintenanceManagerMachineOption;
use App\Repositories\Contracts\MaintenanceAnalyticsRepositoryInterface;
use App\Services\Analytics\MaintenanceKpiService;
use Illuminate\Support\Collection;

final readonly class MaintenanceManagerDashboardService
{
    public function __construct(
        private MaintenanceKpiService $maintenanceKpis,
        private MaintenanceAnalyticsRepositoryInterface $repository,
    ) {
    }

    public function build(
        DashboardFilter $filter
    ): MaintenanceManagerDashboardSnapshot {
        $analysisFilter =
            $this->maintenanceFilter(
                $filter
            );

        /*
         * The option catalogue intentionally ignores the current line and
         * machine selections so a user can move between compatible choices
         * without first resetting the dashboard. The period and maintenance
         * dimensions remain data-backed.
         */
        $catalogueFilter =
            new MaintenanceAnalyticsFilter(
                startDate: $filter->startDate,
                endDate: $filter->endDate,
                timezone: $filter->timezone,
                maintenanceType:
                    $filter->maintenanceType,
                downtimeCategory:
                    $filter->downtimeCategory,
                maximumRangeDays: (int) config(
                    'analytics.maximum_range_days',
                    366
                ),
            );

        return new MaintenanceManagerDashboardSnapshot(
            filter: $filter,
            maintenance:
                $this->maintenanceKpis
                    ->summarize(
                        $analysisFilter
                    ),
            productionLines:
                $this->lineOptions(
                    $this->repository
                        ->filterableProductionLines(
                            $catalogueFilter
                        )
                ),
            machines:
                $this->machineOptions(
                    $this->repository
                        ->filterableMachines(
                            $catalogueFilter
                        )
                ),
        );
    }

    private function maintenanceFilter(
        DashboardFilter $filter
    ): MaintenanceAnalyticsFilter {
        return new MaintenanceAnalyticsFilter(
            startDate: $filter->startDate,
            endDate: $filter->endDate,
            timezone: $filter->timezone,
            productionLineId:
                $filter->productionLineId,
            machineId: $filter->machineId,
            maintenanceType:
                $filter->maintenanceType,
            downtimeCategory:
                $filter->downtimeCategory,
            maximumRangeDays: (int) config(
                'analytics.maximum_range_days',
                366
            ),
        );
    }

    /**
     * @param Collection<int, object> $rows
     * @return list<DashboardFilterOption>
     */
    private function lineOptions(
        Collection $rows
    ): array {
        return $rows
            ->map(
                static fn (
                    object $row
                ): DashboardFilterOption =>
                    new DashboardFilterOption(
                        id: (int) $row->id,
                        label: (string) $row->name,
                        filterValue:
                            (string) $row->id,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, object> $rows
     * @return list<MaintenanceManagerMachineOption>
     */
    private function machineOptions(
        Collection $rows
    ): array {
        return $rows
            ->map(
                static fn (
                    object $row
                ): MaintenanceManagerMachineOption =>
                    new MaintenanceManagerMachineOption(
                        id: (int) $row->id,
                        label:
                            (string) $row
                                ->production_line_name
                            .' — '
                            .(string) $row->name,
                        productionLineId:
                            (int) $row
                                ->production_line_id,
                    )
            )
            ->values()
            ->all();
    }
}
