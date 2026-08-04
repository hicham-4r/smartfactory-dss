<?php

namespace App\Services\AI\Inference;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EloquentAutomaticInferenceSourceRepository
{
    /**
     * @return array{
     *   production_lines: list<array{code: string, label: string}>,
     *   quantity_units: list<string>,
     *   production_records: list<array{id: int, label: string}>,
     *   machines: list<array{id: int, label: string}>,
     *   default_forecast_date: ?string,
     *   default_maintenance_date: ?string
     * }
     */
    public function options(): array
    {
        $sourceSystem = $this->sourceSystem();
        $statuses = $this->includedImportStatuses();
        $latestMaintenanceObservation =
            $this->latestMaintenanceObservation();
        $minimumMaintenanceDays = max(
            1,
            (int) config(
                'ai-automatic-inference.minimum_maintenance_days_observed',
                30
            )
        );
        $maintenanceHistoryCutoff =
            $latestMaintenanceObservation instanceof CarbonImmutable
                ? $latestMaintenanceObservation
                    ->subDays($minimumMaintenanceDays)
                    ->startOfDay()
                : null;

        $productionLines = DB::table(
            'production_records as pr'
        )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'pr.production_line_id'
            )
            ->where(
                'pr.source_system',
                $sourceSystem
            )
            ->whereIn(
                'pr.import_status',
                $statuses
            )
            ->where(
                'pr.validation_status',
                'validated'
            )
            ->select([
                'pl.code',
                'pl.name',
            ])
            ->distinct()
            ->orderBy('pl.code')
            ->get()
            ->map(
                static fn (object $row): array => [
                    'code' => (string) $row->code,
                    'label' => trim(
                        (string) $row->code
                        .' — '
                        .(string) $row->name
                    ),
                ]
            )
            ->values()
            ->all();

        $quantityUnits = DB::table(
            'production_records as pr'
        )
            ->where(
                'pr.source_system',
                $sourceSystem
            )
            ->whereIn(
                'pr.import_status',
                $statuses
            )
            ->where(
                'pr.validation_status',
                'validated'
            )
            ->whereNotNull('pr.quantity_unit')
            ->where('pr.quantity_unit', '<>', '')
            ->distinct()
            ->orderBy('pr.quantity_unit')
            ->pluck('pr.quantity_unit')
            ->map(
                static fn (mixed $value): string => trim((string) $value)
            )
            ->filter()
            ->values()
            ->all();

        $recordLimit = max(
            20,
            (int) config(
                'ai-automatic-inference.production_record_option_limit',
                150
            )
        );

        $productionRecords = DB::table(
            'production_records as pr'
        )
            ->join(
                'production_batches as pb',
                'pb.id',
                '=',
                'pr.production_batch_id'
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'pr.production_line_id'
            )
            ->where(
                'pr.source_system',
                $sourceSystem
            )
            ->whereIn(
                'pr.import_status',
                $statuses
            )
            ->where(
                'pr.validation_status',
                'validated'
            )
            ->whereNotNull('pr.started_at')
            ->orderByDesc('pr.production_date')
            ->orderByDesc('pr.started_at')
            ->orderByDesc('pr.id')
            ->limit($recordLimit)
            ->get([
                'pr.id',
                'pr.record_number',
                'pr.production_date',
                'pl.code as line_code',
                'p.code as product_code',
            ])
            ->map(
                static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'label' => sprintf(
                        '%s — %s — %s — %s',
                        (string) $row->production_date,
                        (string) $row->line_code,
                        (string) $row->product_code,
                        (string) $row->record_number,
                    ),
                ]
            )
            ->values()
            ->all();

        $machines = DB::table(
            'machines as m'
        )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'm.production_line_id'
            )
            ->where('m.is_active', true)
            ->where(
                'm.source_system',
                $sourceSystem
            )
            ->when(
                $maintenanceHistoryCutoff
                    instanceof CarbonImmutable,
                function ($query) use (
                    $maintenanceHistoryCutoff,
                    $sourceSystem,
                    $statuses
                ): void {
                    $cutoff =
                        $maintenanceHistoryCutoff
                            ->toDateTimeString();

                    $query->where(
                        function ($history) use (
                            $cutoff,
                            $sourceSystem,
                            $statuses
                        ): void {
                            $history
                                ->whereExists(
                                    function ($events) use (
                                        $cutoff,
                                        $sourceSystem,
                                        $statuses
                                    ): void {
                                        $events
                                            ->selectRaw('1')
                                            ->from(
                                                'production_events as eligible_pe'
                                            )
                                            ->whereColumn(
                                                'eligible_pe.machine_id',
                                                'm.id'
                                            )
                                            ->where(
                                                'eligible_pe.event_type',
                                                'downtime'
                                            )
                                            ->where(
                                                'eligible_pe.source_system',
                                                $sourceSystem
                                            )
                                            ->whereIn(
                                                'eligible_pe.import_status',
                                                $statuses
                                            )
                                            ->where(
                                                'eligible_pe.started_at',
                                                '<=',
                                                $cutoff
                                            );
                                    }
                                )
                                ->orWhereExists(
                                    function ($statusEvents) use (
                                        $cutoff,
                                        $statuses
                                    ): void {
                                        $statusEvents
                                            ->selectRaw('1')
                                            ->from(
                                                'machine_status_events as eligible_mse'
                                            )
                                            ->whereNotNull(
                                                'm.external_id'
                                            )
                                            ->whereColumn(
                                                'eligible_mse.machine_external_id',
                                                'm.external_id'
                                            )
                                            ->whereIn(
                                                'eligible_mse.import_status',
                                                $statuses
                                            )
                                            ->where(
                                                'eligible_mse.occurred_at',
                                                '<=',
                                                $cutoff
                                            );
                                    }
                                )
                                ->orWhereExists(
                                    function ($maintenanceEvents) use (
                                        $cutoff,
                                        $statuses
                                    ): void {
                                        $maintenanceEvents
                                            ->selectRaw('1')
                                            ->from(
                                                'maintenance_history as eligible_mh'
                                            )
                                            ->whereNotNull(
                                                'm.external_id'
                                            )
                                            ->whereColumn(
                                                'eligible_mh.machine_external_id',
                                                'm.external_id'
                                            )
                                            ->whereIn(
                                                'eligible_mh.import_status',
                                                $statuses
                                            )
                                            ->whereNotNull(
                                                'eligible_mh.scheduled_at'
                                            )
                                            ->where(
                                                'eligible_mh.scheduled_at',
                                                '<=',
                                                $cutoff
                                            );
                                    }
                                );
                        }
                    );
                },
                static function ($query): void {
                    $query->whereRaw('1 = 0');
                }
            )
            ->orderBy('pl.code')
            ->orderBy('m.sequence_number')
            ->orderBy('m.code')
            ->get([
                'm.id',
                'm.code',
                'm.name',
                'm.machine_type',
                'pl.code as line_code',
            ])
            ->map(
                static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'label' => sprintf(
                        '%s — %s — %s (%s)',
                        (string) $row->line_code,
                        (string) $row->code,
                        (string) $row->name,
                        (string) $row->machine_type,
                    ),
                ]
            )
            ->values()
            ->all();

        $latestProductionDate = DB::table(
            'production_records as pr'
        )
            ->where(
                'pr.source_system',
                $sourceSystem
            )
            ->whereIn(
                'pr.import_status',
                $statuses
            )
            ->where(
                'pr.validation_status',
                'validated'
            )
            ->max('pr.production_date');

        return [
            'production_lines' => $productionLines,
            'quantity_units' => $quantityUnits,
            'production_records' => $productionRecords,
            'machines' => $machines,
            'default_forecast_date' => $this->nextDate($latestProductionDate),
            'default_maintenance_date' => $this->nextDate(
                $latestMaintenanceObservation
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forecastRows(
        string $productionLineCode,
        string $quantityUnit,
        CarbonImmutable $predictionDate,
    ): array {
        $maximumHistoryDays = max(
            30,
            (int) config(
                'ai-automatic-inference.maximum_history_days',
                3660
            )
        );

        return DB::table(
            'production_records as pr'
        )
            ->join(
                'production_batches as pb',
                'pb.id',
                '=',
                'pr.production_batch_id'
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'pr.production_line_id'
            )
            ->where(
                'pr.source_system',
                $this->sourceSystem()
            )
            ->whereIn(
                'pr.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'pr.validation_status',
                'validated'
            )
            ->where(
                'pl.code',
                $productionLineCode
            )
            ->where(
                'pr.quantity_unit',
                $quantityUnit
            )
            ->where(
                'pr.production_date',
                '>=',
                $predictionDate
                    ->subDays($maximumHistoryDays)
                    ->toDateString()
            )
            ->where(
                'pr.production_date',
                '<',
                $predictionDate->toDateString()
            )
            ->orderBy('pr.production_date')
            ->orderBy('pr.started_at')
            ->orderBy('pr.id')
            ->get([
                'pr.production_date',
                'pl.code as production_line_code',
                'pr.quantity_unit',
                'po.target_quantity',
                'pr.produced_quantity',
                'pr.good_quantity',
                'pr.rejected_quantity',
                'pr.runtime_minutes',
                'pr.downtime_minutes',
            ])
            ->map(
                static fn (object $row): array => [
                    'production_date' => (string) $row->production_date,
                    'production_line_code' => (string) $row->production_line_code,
                    'quantity_unit' => (string) $row->quantity_unit,
                    'target_quantity' => (float) $row->target_quantity,
                    'produced_quantity' => (float) $row->produced_quantity,
                    'good_quantity' => (float) $row->good_quantity,
                    'rejected_quantity' => (float) $row->rejected_quantity,
                    'runtime_minutes' => (int) $row->runtime_minutes,
                    'downtime_minutes' => (int) $row->downtime_minutes,
                ]
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function productionRecord(int $productionRecordId): ?array
    {
        $row = DB::table(
            'production_records as pr'
        )
            ->join(
                'production_batches as pb',
                'pb.id',
                '=',
                'pr.production_batch_id'
            )
            ->join(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->join(
                'product_families as pf',
                'pf.id',
                '=',
                'p.product_family_id'
            )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'pr.production_line_id'
            )
            ->join(
                'shifts as s',
                's.id',
                '=',
                'pr.shift_id'
            )
            ->where('pr.id', $productionRecordId)
            ->where(
                'pr.source_system',
                $this->sourceSystem()
            )
            ->whereIn(
                'pr.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'pr.validation_status',
                'validated'
            )
            ->whereNotNull('pr.started_at')
            ->first([
                'pr.started_at',
                'pl.code as production_line_code',
                'pf.code as product_family_code',
                'p.code as product_code',
                's.code as shift_code',
                'pr.quantity_unit',
                'po.priority as production_order_priority',
                'po.target_quantity',
                'pr.produced_quantity',
                'pr.good_quantity',
                'pr.rejected_quantity',
                'pr.runtime_minutes',
                'pr.downtime_minutes',
                'pr.validation_status',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'started_at_utc' => $this->utc($row->started_at),
            'production_line_code' => (string) $row->production_line_code,
            'product_family_code' => (string) $row->product_family_code,
            'product_code' => (string) $row->product_code,
            'shift_code' => (string) $row->shift_code,
            'quantity_unit' => (string) $row->quantity_unit,
            'production_order_priority' => (int) $row->production_order_priority,
            'target_quantity' => (float) $row->target_quantity,
            'produced_quantity' => (float) $row->produced_quantity,
            'good_quantity' => (float) $row->good_quantity,
            'rejected_quantity' => (float) $row->rejected_quantity,
            'runtime_minutes' => (int) $row->runtime_minutes,
            'downtime_minutes' => (int) $row->downtime_minutes,
            'is_validated' => (string) $row->validation_status
                    === 'validated',
        ];
    }

    /**
     * @return array{
     *   machine: array<string, mixed>,
     *   statuses: list<array<string, mixed>>,
     *   downtime: list<array<string, mixed>>,
     *   maintenance: list<array<string, mixed>>
     * }|null
     */
    public function machineContext(
        int $machineId,
        CarbonImmutable $predictionDate,
    ): ?array {
        $machine = DB::table(
            'machines as m'
        )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'm.production_line_id'
            )
            ->where('m.id', $machineId)
            ->where('m.is_active', true)
            ->where(
                'm.source_system',
                $this->sourceSystem()
            )
            ->first([
                'm.id',
                'm.external_id',
                'm.code as machine_code',
                'm.machine_type',
                'm.is_critical',
                'pl.code as production_line_code',
            ]);

        if ($machine === null) {
            return null;
        }

        $maximumHistoryDays = max(
            30,
            (int) config(
                'ai-automatic-inference.maximum_history_days',
                3660
            )
        );

        $historyStart = $predictionDate
            ->subDays($maximumHistoryDays)
            ->startOfDay();
        $historyEnd = $predictionDate->startOfDay();

        $statuses = collect();

        if (
            $machine->external_id !== null
            && trim((string) $machine->external_id) !== ''
        ) {
            $statuses = DB::table(
                'machine_status_events as mse'
            )
                ->where(
                    'mse.machine_external_id',
                    $machine->external_id
                )
                ->whereIn(
                    'mse.import_status',
                    $this->includedImportStatuses()
                )
                ->where(
                    'mse.occurred_at',
                    '>=',
                    $historyStart->toDateTimeString()
                )
                ->where(
                    'mse.occurred_at',
                    '<',
                    $historyEnd->toDateTimeString()
                )
                ->orderBy('mse.occurred_at')
                ->orderBy('mse.id')
                ->get([
                    'mse.occurred_at',
                    'mse.status',
                    'mse.duration_minutes',
                ]);
        }

        $downtime = DB::table(
            'production_events as pe'
        )
            ->where('pe.machine_id', $machine->id)
            ->where('pe.event_type', 'downtime')
            ->where(
                'pe.source_system',
                $this->sourceSystem()
            )
            ->whereIn(
                'pe.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'pe.started_at',
                '>=',
                $historyStart->toDateTimeString()
            )
            ->where(
                'pe.started_at',
                '<',
                $historyEnd->toDateTimeString()
            )
            ->orderBy('pe.started_at')
            ->orderBy('pe.id')
            ->get([
                'pe.started_at',
                'pe.severity',
                'pe.category',
                'pe.downtime_type',
                'pe.duration_minutes',
            ]);

        $maintenance = collect();

        if (
            $machine->external_id !== null
            && trim((string) $machine->external_id) !== ''
        ) {
            $maintenance = DB::table(
                'maintenance_history as mh'
            )
                ->where(
                    'mh.machine_external_id',
                    $machine->external_id
                )
                ->whereIn(
                    'mh.import_status',
                    $this->includedImportStatuses()
                )
                ->whereNotNull('mh.scheduled_at')
                ->where(
                    'mh.scheduled_at',
                    '>=',
                    $historyStart->toDateTimeString()
                )
                ->where(
                    'mh.scheduled_at',
                    '<',
                    $historyEnd->toDateTimeString()
                )
                ->orderBy('mh.scheduled_at')
                ->orderBy('mh.id')
                ->get([
                    'mh.scheduled_at',
                    'mh.maintenance_type',
                    'mh.downtime_minutes',
                ]);
        }

        return [
            'machine' => [
                'production_line_code' => (string) $machine->production_line_code,
                'machine_code' => (string) $machine->machine_code,
                'machine_type' => (string) $machine->machine_type,
                'is_critical' => (bool) $machine->is_critical,
            ],
            'statuses' => $statuses
                ->map(
                    fn (object $row): array => [
                        'occurred_at_utc' => $this->utc($row->occurred_at),
                        'status' => (string) $row->status,
                        'duration_minutes' => $row->duration_minutes === null
                                ? 0
                                : (int) $row->duration_minutes,
                    ]
                )
                ->values()
                ->all(),
            'downtime' => $downtime
                ->map(
                    fn (object $row): array => [
                        'started_at_utc' => $this->utc($row->started_at),
                        'severity' => (string) ($row->severity ?? ''),
                        'category' => (string) ($row->category ?? ''),
                        'downtime_type' => (string) ($row->downtime_type ?? ''),
                        'duration_minutes' => $row->duration_minutes === null
                                ? 0
                                : (int) $row->duration_minutes,
                    ]
                )
                ->values()
                ->all(),
            'maintenance' => $maintenance
                ->map(
                    fn (object $row): array => [
                        'scheduled_at_utc' => $this->utc($row->scheduled_at),
                        'maintenance_type' => (string) $row->maintenance_type,
                        'downtime_minutes' => (int) $row->downtime_minutes,
                    ]
                )
                ->values()
                ->all(),
        ];
    }

    private function latestMaintenanceObservation(): mixed
    {
        $values = [
            DB::table('production_events')
                ->where('event_type', 'downtime')
                ->where(
                    'source_system',
                    $this->sourceSystem()
                )
                ->whereIn(
                    'import_status',
                    $this->includedImportStatuses()
                )
                ->max('started_at'),

            DB::table('machine_status_events')
                ->whereIn(
                    'import_status',
                    $this->includedImportStatuses()
                )
                ->max('occurred_at'),

            DB::table('maintenance_history')
                ->whereIn(
                    'import_status',
                    $this->includedImportStatuses()
                )
                ->max('scheduled_at'),
        ];

        $dates = array_values(
            array_filter(
                array_map(
                    static function (mixed $value): ?CarbonImmutable {
                        if ($value === null || $value === '') {
                            return null;
                        }

                        return CarbonImmutable::parse(
                            (string) $value,
                            'UTC'
                        )->utc();
                    },
                    $values
                )
            )
        );

        if ($dates === []) {
            return null;
        }

        usort(
            $dates,
            static fn (
                CarbonImmutable $left,
                CarbonImmutable $right
            ): int => $right->getTimestamp() <=> $left->getTimestamp()
        );

        return $dates[0];
    }

    private function nextDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse(
            (string) $value,
            'UTC'
        )
            ->startOfDay()
            ->addDay()
            ->toDateString();
    }

    private function utc(mixed $value): string
    {
        return CarbonImmutable::parse(
            (string) $value,
            'UTC'
        )
            ->utc()
            ->toIso8601String();
    }

    private function sourceSystem(): string
    {
        $value = config(
            'ai-automatic-inference.source_system',
            'simulated_sage'
        );

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : 'simulated_sage';
    }

    /**
     * @return list<string>
     */
    private function includedImportStatuses(): array
    {
        $values = config(
            'ai-automatic-inference.included_import_statuses',
            [
                'not_applicable',
                'imported',
                'skipped',
            ]
        );

        if (! is_array($values)) {
            return [
                'not_applicable',
                'imported',
                'skipped',
            ];
        }

        $statuses = array_values(
            array_filter(
                array_map(
                    static fn (mixed $value): string => is_string($value)
                            ? trim($value)
                            : '',
                    $values
                ),
                static fn (string $value): bool => $value !== ''
            )
        );

        return $statuses !== []
            ? $statuses
            : [
                'not_applicable',
                'imported',
                'skipped',
            ];
    }
}
