<?php

namespace App\Services\Dashboard;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\DTOs\Dashboard\DashboardFilter;
use App\DTOs\Dashboard\DashboardModuleCard;
use App\DTOs\Dashboard\DashboardOverview;
use App\DTOs\Dashboard\MaintenanceDashboardSnapshot;
use App\DTOs\Dashboard\OperatorDashboardSnapshot;
use App\DTOs\Dashboard\ProductionDashboardSnapshot;
use App\DTOs\Dashboard\QualityDashboardSnapshot;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Analytics\MaintenanceKpiService;
use App\Services\Analytics\ProductionKpiService;
use App\Services\Analytics\QualityKpiService;
use Carbon\CarbonImmutable;

final readonly class DashboardOverviewService
{
    public function __construct(
        private ProductionKpiService $productionKpis,
        private MaintenanceKpiService $maintenanceKpis,
        private QualityKpiService $qualityKpis,
        private OperatorDashboardService $operatorDashboard,
        private ProductionSupervisorDashboardService $productionSupervisorDashboard,
        private ProductionManagerDashboardService $productionManagerDashboard,
        private MaintenanceManagerDashboardService $maintenanceManagerDashboard,
    ) {
    }

    public function build(
        User $user,
        DashboardFilter $filter
    ): DashboardOverview {
        $primaryRole = $this->primaryRole($user);

        $operatorDashboard =
            $primaryRole === RoleName::Operator
            && $user->can(
                PermissionName::ViewOperatorDashboard->value
            )
                ? $this->operatorDashboard->build(
                    user: $user,
                    filter: $filter,
                )
                : null;

        $productionSupervisor =
            $primaryRole === RoleName::ProductionSupervisor
            && $user->can(
                PermissionName::ViewProductionSupervisorDashboard->value
            )
                ? $this->productionSupervisorDashboard->build($filter)
                : null;

        $productionManager =
            $primaryRole === RoleName::ProductionManager
            && $user->can(
                PermissionName::ViewProductionManagerDashboard->value
            )
                ? $this->productionManagerDashboard->build($filter)
                : null;

        $maintenanceManager =
            $primaryRole === RoleName::MaintenanceManager
            && $user->can(
                PermissionName::ViewMaintenanceManagerDashboard->value
            )
            && $user->can(
                PermissionName::ViewMaintenanceKpis->value
            )
                ? $this->maintenanceManagerDashboard->build($filter)
                : null;

        $production = null;
        $quality = null;

        if ($productionSupervisor !== null) {
            $production = ProductionDashboardSnapshot::fromSummary(
                $productionSupervisor->production
            );
            $quality = QualityDashboardSnapshot::fromSummary(
                $productionSupervisor->quality
            );
        } elseif ($productionManager !== null) {
            $production = ProductionDashboardSnapshot::fromSummary(
                $productionManager->production
            );
            $quality = QualityDashboardSnapshot::fromSummary(
                $productionManager->quality
            );
        } elseif (
            $user->can(PermissionName::ViewProductionKpis->value)
        ) {
            $production = ProductionDashboardSnapshot::fromSummary(
                $this->productionKpis->summarize(
                    new AnalyticsFilter(
                        startDate: $filter->startDate,
                        endDate: $filter->endDate,
                        timezone: $filter->timezone,
                        productionLineId: $filter->productionLineId,
                        productId: $filter->productId,
                        shiftId: $filter->shiftId,
                        status: $filter->status,
                        maximumRangeDays: (int) config(
                            'analytics.maximum_range_days',
                            366
                        ),
                    )
                )
            );

            $quality = QualityDashboardSnapshot::fromSummary(
                $this->qualityKpis->summarize(
                    new QualityAnalyticsFilter(
                        startDate: $filter->startDate,
                        endDate: $filter->endDate,
                        timezone: $filter->timezone,
                        productionLineId: $filter->productionLineId,
                        productId: $filter->productId,
                        maximumRangeDays: (int) config(
                            'analytics.maximum_range_days',
                            366
                        ),
                    )
                )
            );
        }

        if ($maintenanceManager !== null) {
            $maintenance =
                MaintenanceDashboardSnapshot::fromSummary(
                    $maintenanceManager->maintenance
                );
        } else {
            $maintenance = $user->can(
                PermissionName::ViewMaintenanceKpis->value
            )
                ? MaintenanceDashboardSnapshot::fromSummary(
                    $this->maintenanceKpis->summarize(
                        new MaintenanceAnalyticsFilter(
                            startDate: $filter->startDate,
                            endDate: $filter->endDate,
                            timezone: $filter->timezone,
                            productionLineId:
                                $filter->productionLineId,
                            machineId:
                                $filter->machineId,
                            maintenanceType:
                                $filter->maintenanceType,
                            downtimeCategory:
                                $filter->downtimeCategory,
                            maximumRangeDays: (int) config(
                                'analytics.maximum_range_days',
                                366
                            ),
                        )
                    )
                )
                : null;
        }

        return new DashboardOverview(
            filter: $filter,
            generatedAt: CarbonImmutable::now('UTC'),
            primaryRole: $primaryRole,
            modules: $this->modules(
                user: $user,
                role: $primaryRole,
                filter: $filter,
                operatorDashboard:
                    $operatorDashboard,
            ),
            operatorDashboard: $operatorDashboard,
            production: $production,
            maintenance: $maintenance,
            quality: $quality,
            productionSupervisor: $productionSupervisor,
            productionManager: $productionManager,
            maintenanceManager: $maintenanceManager,
        );
    }

    private function primaryRole(User $user): ?RoleName
    {
        foreach (
            [
                RoleName::Administrator,
                RoleName::ProductionManager,
                RoleName::ProductionSupervisor,
                RoleName::MaintenanceManager,
                RoleName::Operator,
            ] as $role
        ) {
            if ($user->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    /** @return list<DashboardModuleCard> */
    private function modules(
        User $user,
        ?RoleName $role,
        DashboardFilter $filter,
        ?OperatorDashboardSnapshot $operatorDashboard,
    ): array {
        $cards = [];
        $productionQuery = $filter->toQuery();
        $qualityQuery = $filter->toQualityQuery();

        if (
            $role === RoleName::Operator
            && $user->can(PermissionName::ViewOperatorDashboard->value)
            && $operatorDashboard?->profileLinked === true
            && $operatorDashboard->operatorActive
        ) {
            $cards[] = new DashboardModuleCard(
                key: 'operator-workspace',
                eyebrow: 'Production execution',
                title: 'Operator workspace',
                description: 'Review assigned production work, record output and report downtime or machine incidents.',
                routeName: 'production.operator.index',
                tone: 'primary',
            );
        }

        if (
            $role === RoleName::ProductionSupervisor
            && $user->can(
                PermissionName::ViewProductionSupervisorDashboard->value
            )
        ) {
            $cards[] = new DashboardModuleCard(
                key: 'supervisor-workspace',
                eyebrow: 'Production workflow',
                title: 'Supervisor workspace',
                description: 'Manage production orders and batches, review operator records and resolve production events.',
                routeName: 'production.supervisor.index',
                tone: 'primary',
            );
        }

        if ($user->can(PermissionName::ViewProductionKpis->value)) {
            $cards[] = new DashboardModuleCard(
                key: 'production-analytics',
                eyebrow: 'Analytics',
                title: 'Production performance',
                description: 'Review execution KPIs, trends and breakdowns by line, shift, product and family.',
                routeName: 'analytics.production.index',
                query: $productionQuery,
                tone: 'success',
            );

            $cards[] = new DashboardModuleCard(
                key: 'quality-analytics',
                eyebrow: 'Analytics',
                title: 'Quality and lot release',
                description: 'Review inspection outcomes, finished-lot decisions and nonconformity indicators.',
                routeName: 'analytics.quality.index',
                query: $qualityQuery,
                tone: 'info',
            );
        }

        if ($user->can(PermissionName::ViewMaintenanceKpis->value)) {
            $maintenanceQuery =
                $filter->toMaintenanceQuery();

            $cards[] = new DashboardModuleCard(
                key: 'maintenance-analytics',
                eyebrow: 'Analytics',
                title: 'Maintenance performance',
                description: 'Review downtime, availability, failures, MTTR, MTBF and maintenance interventions.',
                routeName: 'analytics.maintenance.index',
                query: $maintenanceQuery,
                tone: 'warning',
            );
        }

        if (
            $role === RoleName::Administrator
            && $user->can(PermissionName::ViewAdministratorDashboard->value)
        ) {
            $cards[] = new DashboardModuleCard(
                key: 'administration',
                eyebrow: 'Administration',
                title: 'System administration',
                description: 'Manage accounts, permissions, production master data and security controls.',
                routeName: 'admin.dashboard',
                tone: 'dark',
            );

            if (
                $user->can(PermissionName::ViewSynchronizationLogs->value)
                && $user->can(PermissionName::ViewSystemHealth->value)
            ) {
                $cards[] = new DashboardModuleCard(
                    key: 'erp-monitoring',
                    eyebrow: 'ERP integration',
                    title: 'Synchronization monitoring',
                    description: 'Review connector health, synchronization freshness and sanitized integration failures.',
                    routeName: 'admin.erp-monitoring.index',
                    tone: 'secondary',
                );
            }
        }

        return $cards;
    }
}
