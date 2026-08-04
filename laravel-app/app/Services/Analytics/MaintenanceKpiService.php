<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\DTOs\Analytics\MaintenanceKpiSummary;
use App\DTOs\Analytics\MaintenanceMachineMetric;
use App\DTOs\Analytics\MaintenanceTypeMetric;
use App\Enums\ERP\ErpMaintenanceType;
use App\Repositories\Contracts\MaintenanceAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;

final class MaintenanceKpiService
{
    public function __construct(
        private readonly MaintenanceAnalyticsRepositoryInterface $repository,
        private readonly MaintenanceKpiFormulaService $formula,
    ) {
    }

    public function summarize(
        MaintenanceAnalyticsFilter $filter
    ): MaintenanceKpiSummary {
        /**
         * @var array<int, array<string, int|string>> $machines
         */
        $machines = [];

        foreach (
            $this->repository
                ->downtimeByMachine($filter)
            as $row
        ) {
            $machineId = (int) $row->machine_id;

            $machines[$machineId] =
                $this->baseMachineRow($row);

            $machines[$machineId][
                'downtime_event_count'
            ] = (int) $row->downtime_event_count;

            $machines[$machineId][
                'open_downtime_event_count'
            ] = (int) $row->open_downtime_event_count;

            $machines[$machineId][
                'total_downtime_minutes'
            ] = (int) $row->total_downtime_minutes;

            $machines[$machineId][
                'planned_downtime_minutes'
            ] = (int) $row->planned_downtime_minutes;

            $machines[$machineId][
                'unplanned_downtime_minutes'
            ] = (int) $row->unplanned_downtime_minutes;

            $machines[$machineId][
                'unclassified_downtime_minutes'
            ] = (int) $row->unclassified_downtime_minutes;

            /*
             * Breakdown/equipment-fault/utility-failure downtime is the
             * authoritative failure source for MTBF and failure frequency.
             */
            $machines[$machineId][
                'fault_event_count'
            ] = (int) ($row->failure_event_count ?? 0);
        }

        foreach (
            $this->repository
                ->machineStatusByMachine($filter)
            as $row
        ) {
            $machineId = (int) $row->machine_id;

            $machines[$machineId] =
                array_replace(
                    $this->baseMachineRow($row),
                    $machines[$machineId]
                        ?? []
                );

            $machines[$machineId][
                'observed_status_minutes'
            ] = (int) $row->observed_status_minutes;

            $machines[$machineId][
                'running_minutes'
            ] = (int) $row->running_minutes;

            $statusFaultCount =
                (int) $row->fault_event_count;

            /*
             * Downtime and machine-state feeds may describe the same failure.
             * Use the larger count instead of adding them, preventing obvious
             * double counting while retaining the richer available source.
             */
            $machines[$machineId][
                'fault_event_count'
            ] = max(
                (int) $machines[$machineId][
                    'fault_event_count'
                ],
                $statusFaultCount
            );
        }

        foreach (
            $this->repository
                ->maintenanceByMachine($filter)
            as $row
        ) {
            $machineId = (int) $row->machine_id;

            $machines[$machineId] =
                array_replace(
                    $this->baseMachineRow($row),
                    $machines[$machineId]
                        ?? []
                );

            $machines[$machineId][
                'maintenance_intervention_count'
            ] = (int) $row->maintenance_intervention_count;

            $machines[$machineId][
                'preventive_intervention_count'
            ] = (int) $row->preventive_intervention_count;

            $machines[$machineId][
                'corrective_intervention_count'
            ] = (int) $row->corrective_intervention_count;

            $machines[$machineId][
                'completed_corrective_count'
            ] = (int) $row->completed_corrective_count;

            $machines[$machineId][
                'corrective_repair_minutes'
            ] = (int) $row->corrective_repair_minutes;
        }

        $machineMetrics = [];

        foreach ($machines as $machine) {
            $machineMetrics[] =
                $this->machineMetric($machine);
        }

        usort(
            $machineMetrics,
            static function (
                MaintenanceMachineMetric $left,
                MaintenanceMachineMetric $right
            ): int {
                $downtimeComparison =
                    $right->totalDowntimeMinutes
                    <=> $left->totalDowntimeMinutes;

                if ($downtimeComparison !== 0) {
                    return $downtimeComparison;
                }

                $failureComparison =
                    $right->faultEventCount
                    <=> $left->faultEventCount;

                if ($failureComparison !== 0) {
                    return $failureComparison;
                }

                return strcasecmp(
                    $left->machineName,
                    $right->machineName
                );
            }
        );

        $maintenanceTypes = [];

        foreach (
            $this->repository
                ->maintenanceByType($filter)
            as $row
        ) {
            $type = (string) $row->maintenance_type;

            $maintenanceTypes[] =
                new MaintenanceTypeMetric(
                    maintenanceType: $type,
                    label:
                        ErpMaintenanceType::tryFrom(
                            $type
                        )?->label()
                        ?? ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $type
                            )
                        ),
                    interventionCount:
                        (int) $row->intervention_count,
                    plannedCount:
                        (int) $row->planned_count,
                    inProgressCount:
                        (int) $row->in_progress_count,
                    completedCount:
                        (int) $row->completed_count,
                    cancelledCount:
                        (int) $row->cancelled_count,
                    downtimeMinutes:
                        (int) $row->downtime_minutes,
                );
        }

        $totals = [
            'downtime_event_count' => 0,
            'open_downtime_event_count' => 0,
            'total_downtime_minutes' => 0,
            'planned_downtime_minutes' => 0,
            'unplanned_downtime_minutes' => 0,
            'unclassified_downtime_minutes' => 0,
            'observed_status_minutes' => 0,
            'running_minutes' => 0,
            'fault_event_count' => 0,
            'maintenance_intervention_count' => 0,
            'preventive_intervention_count' => 0,
            'corrective_intervention_count' => 0,
            'completed_corrective_count' => 0,
            'corrective_repair_minutes' => 0,
            'repeated_failure_machine_count' => 0,
        ];

        foreach ($machineMetrics as $machine) {
            $totals['downtime_event_count'] +=
                $machine->downtimeEventCount;

            $totals['open_downtime_event_count'] +=
                $machine->openDowntimeEventCount;

            $totals['total_downtime_minutes'] +=
                $machine->totalDowntimeMinutes;

            $totals['planned_downtime_minutes'] +=
                $machine->plannedDowntimeMinutes;

            $totals['unplanned_downtime_minutes'] +=
                $machine->unplannedDowntimeMinutes;

            $totals['unclassified_downtime_minutes'] +=
                $machine->unclassifiedDowntimeMinutes;

            $totals['observed_status_minutes'] +=
                $machine->observedStatusMinutes;

            $totals['running_minutes'] +=
                $machine->runningMinutes;

            $totals['fault_event_count'] +=
                $machine->faultEventCount;

            $totals['maintenance_intervention_count'] +=
                $machine->maintenanceInterventionCount;

            $totals['preventive_intervention_count'] +=
                $machine->preventiveInterventionCount;

            $totals['corrective_intervention_count'] +=
                $machine->correctiveInterventionCount;

            $totals['completed_corrective_count'] +=
                $machine->completedCorrectiveCount;

            $totals['corrective_repair_minutes'] +=
                $machine->correctiveRepairMinutes;

            if ($machine->hasRepeatedFailures()) {
                $totals['repeated_failure_machine_count']++;
            }
        }

        return new MaintenanceKpiSummary(
            filter: $filter,
            generatedAt:
                CarbonImmutable::now()->utc(),
            machines:
                $machineMetrics,
            maintenanceTypes:
                $maintenanceTypes,
            downtimeEventCount:
                $totals['downtime_event_count'],
            openDowntimeEventCount:
                $totals['open_downtime_event_count'],
            totalDowntimeMinutes:
                $totals['total_downtime_minutes'],
            plannedDowntimeMinutes:
                $totals['planned_downtime_minutes'],
            unplannedDowntimeMinutes:
                $totals['unplanned_downtime_minutes'],
            unclassifiedDowntimeMinutes:
                $totals['unclassified_downtime_minutes'],
            observedStatusMinutes:
                $totals['observed_status_minutes'],
            runningMinutes:
                $totals['running_minutes'],
            faultEventCount:
                $totals['fault_event_count'],
            maintenanceInterventionCount:
                $totals['maintenance_intervention_count'],
            preventiveInterventionCount:
                $totals['preventive_intervention_count'],
            correctiveInterventionCount:
                $totals['corrective_intervention_count'],
            completedCorrectiveCount:
                $totals['completed_corrective_count'],
            correctiveRepairMinutes:
                $totals['corrective_repair_minutes'],
            repeatedFailureMachineCount:
                $totals['repeated_failure_machine_count'],
            availabilityPercentage:
                $this->formula->percentage(
                    numerator:
                        $totals['running_minutes'],
                    denominator:
                        $totals['observed_status_minutes']
                ),
            mttrMinutes:
                $this->formula->averageMinutes(
                    totalMinutes:
                        $totals['corrective_repair_minutes'],
                    occurrenceCount:
                        $totals['completed_corrective_count']
                ),
            mtbfMinutes:
                $this->formula->mtbfMinutes(
                    runningMinutes:
                        $totals['running_minutes'],
                    failureCount:
                        $totals['fault_event_count']
                ),
            failuresPer100RunningHours:
                $this->formula
                    ->failuresPer100RunningHours(
                        failureCount:
                            $totals['fault_event_count'],
                        runningMinutes:
                            $totals['running_minutes']
                    ),
        );
    }

    /**
     * @param object $row
     *
     * @return array<string, int|string>
     */
    private function baseMachineRow(
        object $row
    ): array {
        return [
            'machine_id' =>
                (int) $row->machine_id,

            'machine_code' =>
                (string) $row->machine_code,

            'machine_name' =>
                (string) $row->machine_name,

            'production_line_id' =>
                (int) $row->production_line_id,

            'production_line_name' =>
                (string) $row->production_line_name,

            'downtime_event_count' => 0,
            'open_downtime_event_count' => 0,
            'total_downtime_minutes' => 0,
            'planned_downtime_minutes' => 0,
            'unplanned_downtime_minutes' => 0,
            'unclassified_downtime_minutes' => 0,
            'observed_status_minutes' => 0,
            'running_minutes' => 0,
            'fault_event_count' => 0,
            'maintenance_intervention_count' => 0,
            'preventive_intervention_count' => 0,
            'corrective_intervention_count' => 0,
            'completed_corrective_count' => 0,
            'corrective_repair_minutes' => 0,
        ];
    }

    /**
     * @param array<string, int|string> $machine
     */
    private function machineMetric(
        array $machine
    ): MaintenanceMachineMetric {
        $runningMinutes =
            (int) $machine['running_minutes'];

        $observedStatusMinutes =
            (int) $machine[
                'observed_status_minutes'
            ];

        $faultEventCount =
            (int) $machine['fault_event_count'];

        $completedCorrectiveCount =
            (int) $machine[
                'completed_corrective_count'
            ];

        $correctiveRepairMinutes =
            (int) $machine[
                'corrective_repair_minutes'
            ];

        return new MaintenanceMachineMetric(
            machineId:
                (int) $machine['machine_id'],
            machineCode:
                (string) $machine['machine_code'],
            machineName:
                (string) $machine['machine_name'],
            productionLineId:
                (int) $machine['production_line_id'],
            productionLineName:
                (string) $machine[
                    'production_line_name'
                ],
            downtimeEventCount:
                (int) $machine[
                    'downtime_event_count'
                ],
            openDowntimeEventCount:
                (int) $machine[
                    'open_downtime_event_count'
                ],
            totalDowntimeMinutes:
                (int) $machine[
                    'total_downtime_minutes'
                ],
            plannedDowntimeMinutes:
                (int) $machine[
                    'planned_downtime_minutes'
                ],
            unplannedDowntimeMinutes:
                (int) $machine[
                    'unplanned_downtime_minutes'
                ],
            unclassifiedDowntimeMinutes:
                (int) $machine[
                    'unclassified_downtime_minutes'
                ],
            observedStatusMinutes:
                $observedStatusMinutes,
            runningMinutes:
                $runningMinutes,
            faultEventCount:
                $faultEventCount,
            maintenanceInterventionCount:
                (int) $machine[
                    'maintenance_intervention_count'
                ],
            preventiveInterventionCount:
                (int) $machine[
                    'preventive_intervention_count'
                ],
            correctiveInterventionCount:
                (int) $machine[
                    'corrective_intervention_count'
                ],
            completedCorrectiveCount:
                $completedCorrectiveCount,
            correctiveRepairMinutes:
                $correctiveRepairMinutes,
            availabilityPercentage:
                $this->formula->percentage(
                    numerator: $runningMinutes,
                    denominator:
                        $observedStatusMinutes
                ),
            mttrMinutes:
                $this->formula->averageMinutes(
                    totalMinutes:
                        $correctiveRepairMinutes,
                    occurrenceCount:
                        $completedCorrectiveCount
                ),
            mtbfMinutes:
                $this->formula->mtbfMinutes(
                    runningMinutes:
                        $runningMinutes,
                    failureCount:
                        $faultEventCount
                ),
            failuresPer100RunningHours:
                $this->formula
                    ->failuresPer100RunningHours(
                        failureCount:
                            $faultEventCount,
                        runningMinutes:
                            $runningMinutes
                    ),
        );
    }
}
