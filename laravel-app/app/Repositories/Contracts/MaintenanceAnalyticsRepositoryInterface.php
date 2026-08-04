<?php

namespace App\Repositories\Contracts;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use Illuminate\Support\Collection;

interface MaintenanceAnalyticsRepositoryInterface
{
    /**
     * Return database-aggregated downtime metrics grouped by machine.
     *
     * @return list<object>
     */
    public function downtimeByMachine(
        MaintenanceAnalyticsFilter $filter
    ): array;

    /**
     * Return database-aggregated machine-status metrics grouped by machine.
     *
     * @return list<object>
     */
    public function machineStatusByMachine(
        MaintenanceAnalyticsFilter $filter
    ): array;

    /**
     * Return database-aggregated maintenance metrics grouped by machine.
     *
     * @return list<object>
     */
    public function maintenanceByMachine(
        MaintenanceAnalyticsFilter $filter
    ): array;

    /**
     * Return intervention counts grouped by maintenance type.
     *
     * @return list<object>
     */
    public function maintenanceByType(
        MaintenanceAnalyticsFilter $filter
    ): array;

    /**
     * Return active production lines that have at least one maintenance,
     * downtime, or machine-status record relevant to the selected period.
     *
     * @return Collection<int, object>
     */
    public function filterableProductionLines(
        MaintenanceAnalyticsFilter $filter
    ): Collection;

    /**
     * Return active machines that have at least one maintenance, downtime, or
     * machine-status record relevant to the selected period.
     *
     * The complete period-backed set is returned so the browser can immediately
     * filter machines when the production-line selection changes.
     *
     * @return Collection<int, object>
     */
    public function filterableMachines(
        MaintenanceAnalyticsFilter $filter
    ): Collection;
}
