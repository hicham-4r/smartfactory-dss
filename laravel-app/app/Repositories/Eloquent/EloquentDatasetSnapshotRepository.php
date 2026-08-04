<?php

namespace App\Repositories\Eloquent;

use App\Contracts\AI\Datasets\DatasetSnapshotRepositoryInterface;
use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\Enums\AI\DatasetType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;

final class EloquentDatasetSnapshotRepository implements
    DatasetSnapshotRepositoryInterface
{
    public function rows(
        DatasetType $dataset,
        DatasetSnapshotRequest $request
    ): LazyCollection {
        return $this
            ->query($dataset, $request)
            ->cursor()
            ->map(
                fn (object $row): array =>
                    $this->normalize(
                        $dataset,
                        $row
                    )
            );
    }

    private function query(
        DatasetType $dataset,
        DatasetSnapshotRequest $request
    ): Builder {
        return match ($dataset) {
            DatasetType::ProductionRecords =>
                $this->productionRecords($request),

            DatasetType::DowntimeEvents =>
                $this->downtimeEvents($request),

            DatasetType::MachineStatusEvents =>
                $this->machineStatusEvents($request),

            DatasetType::MaintenanceHistory =>
                $this->maintenanceHistory($request),

            DatasetType::QualityInspections =>
                $this->qualityInspections($request),

            DatasetType::FinishedLots =>
                $this->finishedLots($request),

            DatasetType::Nonconformities =>
                $this->nonconformities($request),
        };
    }

    private function productionRecords(
        DatasetSnapshotRequest $request
    ): Builder {
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
            ->where(
                'pr.source_system',
                $request->sourceSystem
            )
            ->whereIn(
                'pr.import_status',
                $this->includedImportStatuses()
            )
            ->whereBetween(
                'pr.production_date',
                [
                    $request
                        ->startDateString(),
                    $request
                        ->endDateString(),
                ]
            )
            ->orderBy('pr.production_date')
            ->orderBy('pr.started_at')
            ->orderBy('pr.id')
            ->select([
                'pr.production_date',
                'pr.started_at',
                'pr.ended_at',
                'pl.code as production_line_code',
                'pf.code as product_family_code',
                'p.code as product_code',
                's.code as shift_code',
                'po.status as production_order_status',
                'po.priority as production_order_priority',
                'pr.status as record_status',
                'pr.validation_status',
                'pr.quantity_unit',
                'po.target_quantity',
                'pr.produced_quantity',
                'pr.good_quantity',
                'pr.rejected_quantity',
                'pr.runtime_minutes',
                'pr.downtime_minutes',
                'pr.import_status',
                'pr.source_version',
                'pr.source_updated_at',
            ]);
    }

    private function downtimeEvents(
        DatasetSnapshotRequest $request
    ): Builder {
        return DB::table(
            'production_events as pe'
        )
            ->join(
                'production_lines as pl',
                'pl.id',
                '=',
                'pe.production_line_id'
            )
            ->leftJoin(
                'machines as m',
                'm.id',
                '=',
                'pe.machine_id'
            )
            ->leftJoin(
                'shifts as s',
                's.id',
                '=',
                'pe.shift_id'
            )
            ->where(
                'pe.event_type',
                'downtime'
            )
            ->where(
                'pe.source_system',
                $request->sourceSystem
            )
            ->whereIn(
                'pe.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'pe.started_at',
                '>=',
                $request->utcStart()
            )
            ->where(
                'pe.started_at',
                '<',
                $request
                    ->utcEndExclusive()
            )
            ->orderBy('pe.started_at')
            ->orderBy('pe.id')
            ->select([
                'pe.started_at',
                'pe.ended_at',
                'pl.code as production_line_code',
                'm.code as machine_code',
                'm.machine_type',
                's.code as shift_code',
                'pe.severity',
                'pe.category',
                'pe.downtime_type',
                'pe.duration_minutes',
                'pe.is_resolved',
                'pe.import_status',
                'pe.source_version',
                'pe.source_updated_at',
            ]);
    }

    private function machineStatusEvents(
        DatasetSnapshotRequest $request
    ): Builder {
        return DB::table(
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
                '>=',
                $request->utcStart()
            )
            ->where(
                'mse.occurred_at',
                '<',
                $request
                    ->utcEndExclusive()
            )
            ->orderBy('mse.occurred_at')
            ->orderBy('mse.id')
            ->select([
                'mse.occurred_at',
                'mse.ended_at',
                'pl.code as production_line_code',
                'm.code as machine_code',
                'm.machine_type',
                'm.is_critical',
                'mse.status',
                'mse.duration_minutes',
                'mse.import_status',
                'mse.source_version',
                'mse.source_updated_at',
            ]);
    }

    private function maintenanceHistory(
        DatasetSnapshotRequest $request
    ): Builder {
        $dateExpression =
            'COALESCE('
            .'mh.started_at, '
            .'mh.scheduled_at, '
            .'mh.completed_at, '
            .'mh.source_updated_at'
            .')';

        return DB::table(
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
                $dateExpression.' >= ?',
                [
                    $request
                        ->utcStart()
                        ->toDateTimeString(),
                ]
            )
            ->whereRaw(
                $dateExpression.' < ?',
                [
                    $request
                        ->utcEndExclusive()
                        ->toDateTimeString(),
                ]
            )
            ->orderByRaw($dateExpression)
            ->orderBy('mh.id')
            ->select([
                'mh.scheduled_at',
                'mh.started_at',
                'mh.completed_at',
                'pl.code as production_line_code',
                'm.code as machine_code',
                'm.machine_type',
                'm.is_critical',
                'mh.maintenance_type',
                'mh.status',
                'mh.downtime_minutes',
                'mh.cost',
                'mh.currency',
                'mh.import_status',
                'mh.source_version',
                'mh.source_updated_at',
            ]);
    }

    private function qualityInspections(
        DatasetSnapshotRequest $request
    ): Builder {
        return DB::table(
            'inspections as i'
        )
            ->join(
                'production_batches as pb',
                'pb.external_id',
                '=',
                'i.batch_external_id'
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
                'po.production_line_id'
            )
            ->whereIn(
                'i.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'i.inspected_at',
                '>=',
                $request->utcStart()
            )
            ->where(
                'i.inspected_at',
                '<',
                $request
                    ->utcEndExclusive()
            )
            ->orderBy('i.inspected_at')
            ->orderBy('i.id')
            ->select([
                'i.inspected_at',
                'pl.code as production_line_code',
                'pf.code as product_family_code',
                'p.code as product_code',
                'i.inspection_type',
                'i.result',
                'i.sample_size',
                'i.passed_quantity',
                'i.failed_quantity',
                'i.import_status',
                'i.source_version',
                'i.source_updated_at',
            ]);
    }

    private function finishedLots(
        DatasetSnapshotRequest $request
    ): Builder {
        return DB::table(
            'finished_lots as fl'
        )
            ->join(
                'production_batches as pb',
                'pb.external_id',
                '=',
                'fl.batch_external_id'
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
                'po.production_line_id'
            )
            ->whereIn(
                'fl.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'fl.produced_at',
                '>=',
                $request->utcStart()
            )
            ->where(
                'fl.produced_at',
                '<',
                $request
                    ->utcEndExclusive()
            )
            ->orderBy('fl.produced_at')
            ->orderBy('fl.id')
            ->select([
                'fl.produced_at',
                'fl.expiry_date',
                'fl.released_at',
                'pl.code as production_line_code',
                'pf.code as product_family_code',
                'p.code as product_code',
                'fl.status',
                'fl.quantity_unit',
                'fl.produced_quantity',
                'fl.released_quantity',
                'fl.rejected_quantity',
                'fl.import_status',
                'fl.source_version',
                'fl.source_updated_at',
            ]);
    }

    private function nonconformities(
        DatasetSnapshotRequest $request
    ): Builder {
        return DB::table(
            'nonconformities as nc'
        )
            ->leftJoin(
                'production_batches as pb',
                'pb.external_id',
                '=',
                'nc.batch_external_id'
            )
            ->leftJoin(
                'production_orders as po',
                'po.id',
                '=',
                'pb.production_order_id'
            )
            ->leftJoin(
                'products as p',
                'p.id',
                '=',
                'po.product_id'
            )
            ->leftJoin(
                'product_families as pf',
                'pf.id',
                '=',
                'p.product_family_id'
            )
            ->leftJoin(
                'production_lines as pl',
                'pl.id',
                '=',
                'po.production_line_id'
            )
            ->whereIn(
                'nc.import_status',
                $this->includedImportStatuses()
            )
            ->where(
                'nc.detected_at',
                '>=',
                $request->utcStart()
            )
            ->where(
                'nc.detected_at',
                '<',
                $request
                    ->utcEndExclusive()
            )
            ->orderBy('nc.detected_at')
            ->orderBy('nc.id')
            ->select([
                'nc.detected_at',
                'nc.corrected_at',
                'pl.code as production_line_code',
                'pf.code as product_family_code',
                'p.code as product_code',
                'nc.severity',
                'nc.status',
                'nc.category',
                'nc.import_status',
                'nc.source_version',
                'nc.source_updated_at',
            ]);
    }

    /**
     * @return list<string>
     */
    private function includedImportStatuses(): array
    {
        $statuses = config(
            'analytics.included_import_statuses',
            [
                'not_applicable',
                'imported',
                'skipped',
            ]
        );

        if (! is_array($statuses)) {
            return [
                'not_applicable',
                'imported',
                'skipped',
            ];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (
                        mixed $status
                    ): string =>
                        is_string($status)
                            ? trim($status)
                            : '',
                    $statuses
                ),
                static fn (
                    string $status
                ): bool => $status !== ''
            )
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function normalize(
        DatasetType $dataset,
        object $row
    ): array {
        return match ($dataset) {
            DatasetType::ProductionRecords => [
                'production_date' =>
                    $this->date(
                        $row->production_date
                    ),
                'started_at_utc' =>
                    $this->utc(
                        $row->started_at
                    ),
                'ended_at_utc' =>
                    $this->utc(
                        $row->ended_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'product_family_code' =>
                    $this->text(
                        $row->product_family_code
                    ),
                'product_code' =>
                    $this->text(
                        $row->product_code
                    ),
                'shift_code' =>
                    $this->text(
                        $row->shift_code
                    ),
                'production_order_status' =>
                    $this->text(
                        $row->production_order_status
                    ),
                'production_order_priority' =>
                    $this->integer(
                        $row->production_order_priority
                    ),
                'record_status' =>
                    $this->text(
                        $row->record_status
                    ),
                'validation_status' =>
                    $this->text(
                        $row->validation_status
                    ),
                'quantity_unit' =>
                    $this->text(
                        $row->quantity_unit
                    ),
                'target_quantity' =>
                    $this->decimal(
                        $row->target_quantity,
                        3
                    ),
                'produced_quantity' =>
                    $this->decimal(
                        $row->produced_quantity,
                        3
                    ),
                'good_quantity' =>
                    $this->decimal(
                        $row->good_quantity,
                        3
                    ),
                'rejected_quantity' =>
                    $this->decimal(
                        $row->rejected_quantity,
                        3
                    ),
                'runtime_minutes' =>
                    $this->integer(
                        $row->runtime_minutes
                    ),
                'downtime_minutes' =>
                    $this->integer(
                        $row->downtime_minutes
                    ),
                'is_validated' =>
                    $row->validation_status
                        === 'validated'
                            ? 1
                            : 0,
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],

            DatasetType::DowntimeEvents => [
                'started_at_utc' =>
                    $this->utc(
                        $row->started_at
                    ),
                'ended_at_utc' =>
                    $this->utc(
                        $row->ended_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'machine_code' =>
                    $this->text(
                        $row->machine_code
                    ),
                'machine_type' =>
                    $this->text(
                        $row->machine_type
                    ),
                'shift_code' =>
                    $this->text(
                        $row->shift_code
                    ),
                'severity' =>
                    $this->text(
                        $row->severity
                    ),
                'category' =>
                    $this->text(
                        $row->category
                    ),
                'downtime_type' =>
                    $this->text(
                        $row->downtime_type
                    ),
                'duration_minutes' =>
                    $this->nullableInteger(
                        $row->duration_minutes
                    ),
                'is_resolved' =>
                    $this->booleanInteger(
                        $row->is_resolved
                    ),
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],

            DatasetType::MachineStatusEvents => [
                'occurred_at_utc' =>
                    $this->utc(
                        $row->occurred_at
                    ),
                'ended_at_utc' =>
                    $this->utc(
                        $row->ended_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'machine_code' =>
                    $this->text(
                        $row->machine_code
                    ),
                'machine_type' =>
                    $this->text(
                        $row->machine_type
                    ),
                'is_critical' =>
                    $this->booleanInteger(
                        $row->is_critical
                    ),
                'status' =>
                    $this->text(
                        $row->status
                    ),
                'duration_minutes' =>
                    $this->nullableInteger(
                        $row->duration_minutes
                    ),
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],

            DatasetType::MaintenanceHistory => [
                'scheduled_at_utc' =>
                    $this->utc(
                        $row->scheduled_at
                    ),
                'started_at_utc' =>
                    $this->utc(
                        $row->started_at
                    ),
                'completed_at_utc' =>
                    $this->utc(
                        $row->completed_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'machine_code' =>
                    $this->text(
                        $row->machine_code
                    ),
                'machine_type' =>
                    $this->text(
                        $row->machine_type
                    ),
                'is_critical' =>
                    $this->booleanInteger(
                        $row->is_critical
                    ),
                'maintenance_type' =>
                    $this->text(
                        $row->maintenance_type
                    ),
                'status' =>
                    $this->text(
                        $row->status
                    ),
                'downtime_minutes' =>
                    $this->integer(
                        $row->downtime_minutes
                    ),
                'cost' =>
                    $this->nullableDecimal(
                        $row->cost,
                        2
                    ),
                'currency' =>
                    $this->text(
                        $row->currency
                    ),
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],

            DatasetType::QualityInspections => [
                'inspected_at_utc' =>
                    $this->utc(
                        $row->inspected_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'product_family_code' =>
                    $this->text(
                        $row->product_family_code
                    ),
                'product_code' =>
                    $this->text(
                        $row->product_code
                    ),
                'inspection_type' =>
                    $this->text(
                        $row->inspection_type
                    ),
                'result' =>
                    $this->text(
                        $row->result
                    ),
                'sample_size' =>
                    $this->nullableInteger(
                        $row->sample_size
                    ),
                'passed_quantity' =>
                    $this->nullableInteger(
                        $row->passed_quantity
                    ),
                'failed_quantity' =>
                    $this->nullableInteger(
                        $row->failed_quantity
                    ),
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],

            DatasetType::FinishedLots => [
                'produced_at_utc' =>
                    $this->utc(
                        $row->produced_at
                    ),
                'expiry_date' =>
                    $this->date(
                        $row->expiry_date
                    ),
                'released_at_utc' =>
                    $this->utc(
                        $row->released_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'product_family_code' =>
                    $this->text(
                        $row->product_family_code
                    ),
                'product_code' =>
                    $this->text(
                        $row->product_code
                    ),
                'status' =>
                    $this->text(
                        $row->status
                    ),
                'quantity_unit' =>
                    $this->text(
                        $row->quantity_unit
                    ),
                'produced_quantity' =>
                    $this->decimal(
                        $row->produced_quantity,
                        3
                    ),
                'released_quantity' =>
                    $this->decimal(
                        $row->released_quantity,
                        3
                    ),
                'rejected_quantity' =>
                    $this->decimal(
                        $row->rejected_quantity,
                        3
                    ),
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],

            DatasetType::Nonconformities => [
                'detected_at_utc' =>
                    $this->utc(
                        $row->detected_at
                    ),
                'corrected_at_utc' =>
                    $this->utc(
                        $row->corrected_at
                    ),
                'production_line_code' =>
                    $this->text(
                        $row->production_line_code
                    ),
                'product_family_code' =>
                    $this->text(
                        $row->product_family_code
                    ),
                'product_code' =>
                    $this->text(
                        $row->product_code
                    ),
                'severity' =>
                    $this->text(
                        $row->severity
                    ),
                'status' =>
                    $this->text(
                        $row->status
                    ),
                'category' =>
                    $this->text(
                        $row->category
                    ),
                'import_status' =>
                    $this->text(
                        $row->import_status
                    ),
                'source_version' =>
                    $this->nullableInteger(
                        $row->source_version
                    ),
                'source_updated_at_utc' =>
                    $this->utc(
                        $row->source_updated_at
                    ),
            ],
        };
    }

    private function text(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        $normalized = trim(
            preg_replace(
                '/[\x00-\x1F\x7F]+/u',
                ' ',
                (string) $value
            ) ?? ''
        );

        return mb_substr(
            $normalized,
            0,
            500
        );
    }

    private function integer(
        mixed $value
    ): int {
        return max(
            0,
            (int) $value
        );
    }

    private function nullableInteger(
        mixed $value
    ): ?int {
        return $value === null
            ? null
            : max(
                0,
                (int) $value
            );
    }

    private function booleanInteger(
        mixed $value
    ): int {
        return filter_var(
            $value,
            FILTER_VALIDATE_BOOL
        )
            ? 1
            : 0;
    }

    private function decimal(
        mixed $value,
        int $scale
    ): string {
        return number_format(
            max(
                0,
                (float) $value
            ),
            $scale,
            '.',
            ''
        );
    }

    private function nullableDecimal(
        mixed $value,
        int $scale
    ): ?string {
        return $value === null
            ? null
            : $this->decimal(
                $value,
                $scale
            );
    }

    private function date(
        mixed $value
    ): string {
        if ($value === null || $value === '') {
            return '';
        }

        return CarbonImmutable::parse(
            (string) $value,
            'UTC'
        )->toDateString();
    }

    private function utc(
        mixed $value
    ): string {
        if ($value === null || $value === '') {
            return '';
        }

        return CarbonImmutable::parse(
            (string) $value,
            'UTC'
        )
            ->utc()
            ->format(
                'Y-m-d\TH:i:s\Z'
            );
    }
}
