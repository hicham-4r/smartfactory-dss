<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\Enums\ERP\ErpMachineStatus;
use App\Enums\ERP\ErpMaintenanceStatus;
use App\Enums\ERP\ErpMaintenanceType;
use App\Repositories\Contracts\MaintenanceAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentMaintenanceAnalyticsRepository implements
    MaintenanceAnalyticsRepositoryInterface
{
    public function downtimeByMachine(
        MaintenanceAnalyticsFilter $filter
    ): array {
        $categorySql = $this->effectiveDowntimeCategorySql();
        $failureSql = $this->failureDowntimeSql();

        $query = DB::table(
            'production_events as pe'
        )
            ->join(
                'machines as m',
                'm.id',
                '=',
                'pe.machine_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'pe.production_line_id'
            )
            ->where(
                'pe.event_type',
                'downtime'
            )
            ->whereIn(
                'pe.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'pe.started_at',
                '>=',
                $filter->utcStart()
                    ->toDateTimeString()
            )
            ->where(
                'pe.started_at',
                '<',
                $filter->utcEndExclusive()
                    ->toDateTimeString()
            );

        $this->applyMachineFilters(
            query: $query,
            filter: $filter,
            machineAlias: 'm',
            lineAlias: 'pl',
        );

        if ($filter->downtimeCategory !== null) {
            $query->whereRaw(
                $categorySql.' = ?',
                [
                    $filter->downtimeCategory,
                ]
            );
        }

        return $query
            ->groupBy(
                'm.id',
                'm.code',
                'm.name',
                'pl.id',
                'pl.name'
            )
            ->orderByDesc(
                'total_downtime_minutes'
            )
            ->orderBy('m.name')
            ->select([
                'm.id as machine_id',
                'm.code as machine_code',
                'm.name as machine_name',
                'pl.id as production_line_id',
                'pl.name as production_line_name',
            ])
            ->selectRaw(
                'COUNT(pe.id) as downtime_event_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN pe.is_resolved = 0 THEN 1 ELSE 0 END) as open_downtime_event_count'
            )
            ->selectRaw(
                'COALESCE(SUM(pe.duration_minutes), 0) as total_downtime_minutes'
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$categorySql} = 'planned' THEN pe.duration_minutes ELSE 0 END), 0) as planned_downtime_minutes"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$categorySql} = 'unplanned' THEN pe.duration_minutes ELSE 0 END), 0) as unplanned_downtime_minutes"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN {$categorySql} = 'unclassified' THEN pe.duration_minutes ELSE 0 END), 0) as unclassified_downtime_minutes"
            )
            ->selectRaw(
                "SUM(CASE WHEN {$failureSql} THEN 1 ELSE 0 END) as failure_event_count"
            )
            ->get()
            ->all();
    }

    public function machineStatusByMachine(
        MaintenanceAnalyticsFilter $filter
    ): array {
        $query = DB::table(
            'machine_status_events as mse'
        )
            ->join(
                'machines as m',
                'm.external_id',
                '=',
                'mse.machine_external_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'm.production_line_id'
            )
            ->whereIn(
                'mse.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'mse.occurred_at',
                '<',
                $filter->utcEndExclusive()
                    ->toDateTimeString()
            );

        $this->applyMachineFilters(
            query: $query,
            filter: $filter,
            machineAlias: 'm',
            lineAlias: 'pl',
        );

        /*
         * Machine-status rows are state transitions. The simulator may omit
         * ended_at/duration_minutes, so intervals are reconstructed from the
         * next transition and clipped to the selected UTC period. This keeps
         * MySQL and SQLite behavior deterministic without vendor-specific SQL.
         */
        $rows = $query
            ->orderBy('m.id')
            ->orderBy('mse.occurred_at')
            ->orderBy('mse.id')
            ->get([
                'm.id as machine_id',
                'm.code as machine_code',
                'm.name as machine_name',
                'pl.id as production_line_id',
                'pl.name as production_line_name',
                'mse.id as status_event_id',
                'mse.status',
                'mse.occurred_at',
                'mse.ended_at',
                'mse.duration_minutes',
            ]);

        $periodStart = $filter->utcStart();
        $periodEnd = $filter->utcEndExclusive();
        $metrics = [];

        foreach ($rows->groupBy('machine_id') as $machineRows) {
            /** @var Collection<int, object> $machineRows */
            $timeline = $machineRows->values();

            if ($timeline->isEmpty()) {
                continue;
            }

            $latestBeforeStart = null;
            $periodRows = [];

            foreach ($timeline as $row) {
                $occurredAt = $this->asUtc(
                    $row->occurred_at
                );

                if ($occurredAt->lessThan($periodStart)) {
                    $latestBeforeStart = $row;
                    continue;
                }

                if ($occurredAt->lessThan($periodEnd)) {
                    $periodRows[] = $row;
                }
            }

            if ($latestBeforeStart !== null) {
                array_unshift(
                    $periodRows,
                    $latestBeforeStart
                );
            }

            if ($periodRows === []) {
                continue;
            }

            $observedMinutes = 0;
            $runningMinutes = 0;
            $faultTransitions = 0;
            $rowCount = count($periodRows);

            foreach ($periodRows as $index => $row) {
                $eventStart = $this->asUtc(
                    $row->occurred_at
                );

                $intervalStart = $eventStart
                    ->lessThan($periodStart)
                    ? $periodStart
                    : $eventStart;

                if (! $intervalStart->lessThan($periodEnd)) {
                    continue;
                }

                $candidates = [
                    $periodEnd,
                ];

                $explicitEnd = $this->explicitStatusEnd(
                    row: $row,
                    eventStart: $eventStart
                );

                if ($explicitEnd !== null) {
                    $candidates[] = $explicitEnd;
                }

                if ($index + 1 < $rowCount) {
                    $candidates[] = $this->asUtc(
                        $periodRows[$index + 1]
                            ->occurred_at
                    );
                }

                $intervalEnd = $periodEnd;

                foreach ($candidates as $candidate) {
                    if (
                        $candidate->greaterThan($intervalStart)
                        && $candidate->lessThan($intervalEnd)
                    ) {
                        $intervalEnd = $candidate;
                    }
                }

                if (! $intervalEnd->greaterThan($intervalStart)) {
                    continue;
                }

                $minutes = (int) floor(
                    $intervalStart->diffInSeconds(
                        $intervalEnd
                    ) / 60
                );

                if ($minutes <= 0) {
                    continue;
                }

                $observedMinutes += $minutes;

                $status = strtolower(
                    trim((string) $row->status)
                );

                if (
                    $status
                    === ErpMachineStatus::Running->value
                ) {
                    $runningMinutes += $minutes;
                }

                if (
                    $status
                    === ErpMachineStatus::Fault->value
                    && $eventStart
                        ->greaterThanOrEqualTo(
                            $periodStart
                        )
                    && $eventStart
                        ->lessThan($periodEnd)
                ) {
                    $faultTransitions++;
                }
            }

            $first = $periodRows[0];

            $metrics[] = (object) [
                'machine_id' =>
                    (int) $first->machine_id,
                'machine_code' =>
                    (string) $first->machine_code,
                'machine_name' =>
                    (string) $first->machine_name,
                'production_line_id' =>
                    (int) $first->production_line_id,
                'production_line_name' =>
                    (string) $first->production_line_name,
                'observed_status_minutes' =>
                    $observedMinutes,
                'running_minutes' =>
                    $runningMinutes,
                'fault_event_count' =>
                    $faultTransitions,
            ];
        }

        usort(
            $metrics,
            static fn (object $left, object $right): int =>
                strcasecmp(
                    (string) $left->machine_name,
                    (string) $right->machine_name
                )
        );

        return $metrics;
    }

    public function maintenanceByMachine(
        MaintenanceAnalyticsFilter $filter
    ): array {
        $query = $this->maintenanceBaseQuery(
            $filter
        );

        return $query
            ->groupBy(
                'm.id',
                'm.code',
                'm.name',
                'pl.id',
                'pl.name'
            )
            ->orderBy('m.name')
            ->select([
                'm.id as machine_id',
                'm.code as machine_code',
                'm.name as machine_name',
                'pl.id as production_line_id',
                'pl.name as production_line_name',
            ])
            ->selectRaw(
                'COUNT(mh.id) as maintenance_intervention_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.maintenance_type = ? THEN 1 ELSE 0 END) as preventive_intervention_count',
                [
                    ErpMaintenanceType::Preventive->value,
                ]
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.maintenance_type = ? THEN 1 ELSE 0 END) as corrective_intervention_count',
                [
                    ErpMaintenanceType::Corrective->value,
                ]
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.maintenance_type = ? AND mh.status = ? THEN 1 ELSE 0 END) as completed_corrective_count',
                [
                    ErpMaintenanceType::Corrective->value,
                    ErpMaintenanceStatus::Completed->value,
                ]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN mh.maintenance_type = ? AND mh.status = ? THEN mh.downtime_minutes ELSE 0 END), 0) as corrective_repair_minutes',
                [
                    ErpMaintenanceType::Corrective->value,
                    ErpMaintenanceStatus::Completed->value,
                ]
            )
            ->get()
            ->all();
    }

    public function maintenanceByType(
        MaintenanceAnalyticsFilter $filter
    ): array {
        return $this->maintenanceBaseQuery(
            $filter
        )
            ->groupBy(
                'mh.maintenance_type'
            )
            ->orderBy(
                'mh.maintenance_type'
            )
            ->select([
                'mh.maintenance_type',
            ])
            ->selectRaw(
                'COUNT(mh.id) as intervention_count'
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.status = ? THEN 1 ELSE 0 END) as planned_count',
                [
                    ErpMaintenanceStatus::Planned->value,
                ]
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.status = ? THEN 1 ELSE 0 END) as in_progress_count',
                [
                    ErpMaintenanceStatus::InProgress->value,
                ]
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.status = ? THEN 1 ELSE 0 END) as completed_count',
                [
                    ErpMaintenanceStatus::Completed->value,
                ]
            )
            ->selectRaw(
                'SUM(CASE WHEN mh.status = ? THEN 1 ELSE 0 END) as cancelled_count',
                [
                    ErpMaintenanceStatus::Cancelled->value,
                ]
            )
            ->selectRaw(
                'COALESCE(SUM(mh.downtime_minutes), 0) as downtime_minutes'
            )
            ->get()
            ->all();
    }

    public function filterableProductionLines(
        MaintenanceAnalyticsFilter $filter
    ): Collection {
        return DB::table(
            'production_lines as pl'
        )
            ->where('pl.is_active', true)
            ->whereExists(
                function (Builder $machines) use (
                    $filter
                ): void {
                    $machines
                        ->selectRaw('1')
                        ->from('machines as fm')
                        ->whereColumn(
                            'fm.production_line_id',
                            'pl.id'
                        )
                        ->where('fm.is_active', true);

                    $this->applyMaintenanceDataExists(
                        query: $machines,
                        filter: $filter,
                        machineAlias: 'fm'
                    );
                }
            )
            ->orderBy('pl.name')
            ->get([
                'pl.id',
                'pl.code',
                'pl.name',
            ]);
    }

    public function filterableMachines(
        MaintenanceAnalyticsFilter $filter
    ): Collection {
        $query = DB::table(
            'machines as m'
        )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'm.production_line_id'
            )
            ->where('m.is_active', true)
            ->where('pl.is_active', true);

        $this->applyMaintenanceDataExists(
            query: $query,
            filter: $filter,
            machineAlias: 'm'
        );

        return $query
            ->orderBy('pl.name')
            ->orderBy('m.sequence_number')
            ->orderBy('m.name')
            ->get([
                'm.id',
                'm.code',
                'm.name',
                'm.machine_type',
                'm.production_line_id',
                'pl.name as production_line_name',
            ]);
    }

    /**
     * Restrict a machine query to machines that can produce at least one
     * supported maintenance KPI in the selected period.
     */
    private function applyMaintenanceDataExists(
        Builder $query,
        MaintenanceAnalyticsFilter $filter,
        string $machineAlias
    ): void {
        $start = $filter->utcStart()
            ->toDateTimeString();

        $end = $filter->utcEndExclusive()
            ->toDateTimeString();

        $query->where(
            function (Builder $hasData) use (
                $filter,
                $machineAlias,
                $start,
                $end
            ): void {
                $hasData
                    ->whereExists(
                        function (Builder $downtime) use (
                            $filter,
                            $machineAlias,
                            $start,
                            $end
                        ): void {
                            $downtime
                                ->selectRaw('1')
                                ->from(
                                    'production_events as filter_pe'
                                )
                                ->whereColumn(
                                    'filter_pe.machine_id',
                                    $machineAlias.'.id'
                                )
                                ->where(
                                    'filter_pe.event_type',
                                    'downtime'
                                )
                                ->whereIn(
                                    'filter_pe.import_status',
                                    $this->includedImportStatuses()
                                )
                                ->where(
                                    'filter_pe.started_at',
                                    '>=',
                                    $start
                                )
                                ->where(
                                    'filter_pe.started_at',
                                    '<',
                                    $end
                                );

                            if (
                                $filter->downtimeCategory
                                !== null
                            ) {
                                $categorySql =
                                    str_replace(
                                        'pe.',
                                        'filter_pe.',
                                        $this
                                            ->effectiveDowntimeCategorySql()
                                    );

                                $downtime->whereRaw(
                                    $categorySql.' = ?',
                                    [
                                        $filter
                                            ->downtimeCategory,
                                    ]
                                );
                            }
                        }
                    )
                    ->orWhereExists(
                        function (Builder $statuses) use (
                            $machineAlias,
                            $start,
                            $end
                        ): void {
                            $statuses
                                ->selectRaw('1')
                                ->from(
                                    'machine_status_events as filter_mse'
                                )
                                ->whereColumn(
                                    'filter_mse.machine_external_id',
                                    $machineAlias.'.external_id'
                                )
                                ->whereNotNull(
                                    $machineAlias.'.external_id'
                                )
                                ->whereIn(
                                    'filter_mse.import_status',
                                    $this->includedImportStatuses()
                                )
                                ->where(
                                    'filter_mse.occurred_at',
                                    '<',
                                    $end
                                )
                                ->where(
                                    function (
                                        Builder $coverage
                                    ) use (
                                        $start
                                    ): void {
                                        $coverage
                                            ->where(
                                                'filter_mse.occurred_at',
                                                '>=',
                                                $start
                                            )
                                            ->orWhereNull(
                                                'filter_mse.ended_at'
                                            )
                                            ->orWhere(
                                                'filter_mse.ended_at',
                                                '>=',
                                                $start
                                            );
                                    }
                                );
                        }
                    )
                    ->orWhereExists(
                        function (Builder $maintenance) use (
                            $filter,
                            $machineAlias,
                            $start,
                            $end
                        ): void {
                            $maintenance
                                ->selectRaw('1')
                                ->from(
                                    'maintenance_history as filter_mh'
                                )
                                ->whereColumn(
                                    'filter_mh.machine_external_id',
                                    $machineAlias.'.external_id'
                                )
                                ->whereNotNull(
                                    $machineAlias.'.external_id'
                                )
                                ->whereIn(
                                    'filter_mh.import_status',
                                    $this->includedImportStatuses()
                                )
                                ->whereRaw(
                                    'COALESCE(filter_mh.started_at, filter_mh.scheduled_at, filter_mh.completed_at) >= ?',
                                    [
                                        $start,
                                    ]
                                )
                                ->whereRaw(
                                    'COALESCE(filter_mh.started_at, filter_mh.scheduled_at, filter_mh.completed_at) < ?',
                                    [
                                        $end,
                                    ]
                                );

                            if (
                                $filter->maintenanceType
                                !== null
                            ) {
                                $maintenance->where(
                                    'filter_mh.maintenance_type',
                                    $filter
                                        ->maintenanceType
                                );
                            }
                        }
                    );
            }
        );
    }

    private function maintenanceBaseQuery(
        MaintenanceAnalyticsFilter $filter
    ): Builder {
        $query = DB::table(
            'maintenance_history as mh'
        )
            ->join(
                'machines as m',
                'm.external_id',
                '=',
                'mh.machine_external_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'm.production_line_id'
            )
            ->whereIn(
                'mh.import_status',
                $this->includedImportStatuses()
            )
            ->whereRaw(
                'COALESCE(mh.started_at, mh.scheduled_at, mh.completed_at) >= ?',
                [
                    $filter->utcStart()
                        ->toDateTimeString(),
                ]
            )
            ->whereRaw(
                'COALESCE(mh.started_at, mh.scheduled_at, mh.completed_at) < ?',
                [
                    $filter->utcEndExclusive()
                        ->toDateTimeString(),
                ]
            );

        $this->applyMachineFilters(
            query: $query,
            filter: $filter,
            machineAlias: 'm',
            lineAlias: 'pl',
        );

        if ($filter->maintenanceType !== null) {
            $query->where(
                'mh.maintenance_type',
                $filter->maintenanceType
            );
        }

        return $query;
    }

    private function applyMachineFilters(
        Builder $query,
        MaintenanceAnalyticsFilter $filter,
        string $machineAlias,
        string $lineAlias
    ): void {
        if ($filter->productionLineId !== null) {
            $query->where(
                $lineAlias.'.id',
                $filter->productionLineId
            );
        }

        if ($filter->machineId !== null) {
            $query->where(
                $machineAlias.'.id',
                $filter->machineId
            );
        }
    }

    private function effectiveDowntimeCategorySql(): string
    {
        $type = $this->normalizedDowntimeTextSql();

        return "CASE
            WHEN LOWER(TRIM(COALESCE(pe.category, ''))) IN ('planned', 'unplanned')
                THEN LOWER(TRIM(pe.category))
            WHEN {$type} LIKE '%cleaning%'
                OR {$type} LIKE '%changeover%'
                OR {$type} LIKE '%preventive%'
                OR {$type} LIKE '%planned maintenance%'
                OR {$type} LIKE '%scheduled maintenance%'
                OR {$type} LIKE '%sanitation%'
                OR {$type} LIKE '%setup%'
                THEN 'planned'
            WHEN {$type} LIKE '%breakdown%'
                OR {$type} LIKE '%equipment fault%'
                OR {$type} LIKE '%machine fault%'
                OR {$type} LIKE '%utility failure%'
                OR {$type} LIKE '%safety stop%'
                OR {$type} LIKE '%material shortage%'
                OR {$type} LIKE '%quality hold%'
                OR {$type} LIKE '%unplanned%'
                THEN 'unplanned'
            ELSE 'unclassified'
        END";
    }

    private function failureDowntimeSql(): string
    {
        $type = $this->normalizedDowntimeTextSql();

        return "({$type} LIKE '%breakdown%'
            OR {$type} LIKE '%equipment fault%'
            OR {$type} LIKE '%machine fault%'
            OR {$type} LIKE '%utility failure%'
            OR {$type} LIKE '%safety stop%')";
    }

    private function normalizedDowntimeTextSql(): string
    {
        return "LOWER(REPLACE(REPLACE(COALESCE(
            NULLIF(TRIM(pe.downtime_type), ''),
            NULLIF(TRIM(pe.title), ''),
            NULLIF(TRIM(pe.description), ''),
            ''
        ), '_', ' '), '-', ' '))";
    }

    private function explicitStatusEnd(
        object $row,
        CarbonImmutable $eventStart
    ): ?CarbonImmutable {
        if (
            property_exists($row, 'ended_at')
            && $row->ended_at !== null
            && trim((string) $row->ended_at) !== ''
        ) {
            $endedAt = $this->asUtc(
                $row->ended_at
            );

            if ($endedAt->greaterThan($eventStart)) {
                return $endedAt;
            }
        }

        $duration = property_exists(
            $row,
            'duration_minutes'
        )
            ? (int) ($row->duration_minutes ?? 0)
            : 0;

        return $duration > 0
            ? $eventStart->addMinutes($duration)
            : null;
    }

    private function asUtc(
        mixed $value
    ): CarbonImmutable {
        return CarbonImmutable::parse(
            (string) $value,
            'UTC'
        )->utc();
    }

    /**
     * @return list<string>
     */
    private function includedImportStatuses(): array
    {
        return [
            'imported',
            'skipped',
            'not_applicable',
        ];
    }
}
