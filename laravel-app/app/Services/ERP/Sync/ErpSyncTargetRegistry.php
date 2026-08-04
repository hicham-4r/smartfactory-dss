<?php

namespace App\Services\ERP\Sync;

use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpPersistenceException;
use Illuminate\Support\Facades\Schema;

final class ErpSyncTargetRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private array $columnCache = [];

    public function tableFor(
        ErpResource $resource
    ): string {
        foreach (
            $this->tableCandidates($resource)
            as $table
        ) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        throw ErpPersistenceException
            ::missingTargetTable($resource);
    }

    /**
     * @return list<string>
     */
    public function tableCandidates(
        ErpResource $resource
    ): array {
        return match ($resource) {
            ErpResource::ProductFamilies => [
                'product_families',
            ],

            ErpResource::Products => [
                'products',
            ],

            ErpResource::ProductionLines => [
                'production_lines',
            ],

            ErpResource::Machines => [
                'machines',
            ],

            ErpResource::Shifts => [
                'shifts',
            ],

            ErpResource::Operators => [
                'operators',
            ],

            ErpResource::OperatorAssignments => [
                'operator_assignments',
            ],

            /*
             * Prefer the Phase 4 DSS tables before the original
             * simulator source tables.
             */
            ErpResource::WorkOrders => [
                'production_orders',
                'work_orders',
            ],

            ErpResource::Batches => [
                'production_batches',
                'batches',
            ],

            ErpResource::MachineRuns => [
                'production_records',
                'machine_runs',
            ],

            ErpResource::RunLogs => [
                'run_logs',
            ],

            ErpResource::DowntimeEvents => [
                'production_events',
                'downtime_events',
            ],

            ErpResource::MachineStatusEvents => [
                'machine_status_events',
            ],

            ErpResource::MaintenanceHistory => [
                'maintenance_history',
            ],

            ErpResource::Inspections => [
                'inspections',
            ],

            ErpResource::Nonconformities => [
                'nonconformities',
            ],

            ErpResource::FinishedLots => [
                'finished_lots',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function columnsFor(
        string $table
    ): array {
        if (
            ! array_key_exists(
                $table,
                $this->columnCache
            )
        ) {
            $this->columnCache[$table] =
                Schema::getColumnListing($table);
        }

        return $this->columnCache[$table];
    }

    /**
     * @param list<string> $candidates
     * @param list<string> $columns
     */
    public function firstExistingColumn(
        array $candidates,
        array $columns
    ): ?string {
        foreach ($candidates as $candidate) {
            if (
                in_array(
                    $candidate,
                    $columns,
                    true
                )
            ) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Used when source_system/external_id columns are unavailable.
     *
     * @return list<string>
     */
    public function businessKeys(
        ErpResource $resource
    ): array {
        return match ($resource) {
            ErpResource::ProductFamilies => [
                'code',
                'name',
            ],

            ErpResource::Products => [
                'code',
                'sku',
            ],

            ErpResource::ProductionLines => [
                'code',
                'name',
            ],

            ErpResource::Machines => [
                'code',
            ],

            ErpResource::Shifts => [
                'code',
            ],

            ErpResource::Operators => [
                'employee_number',
                'employee_code',
                'email',
            ],

            ErpResource::OperatorAssignments => [
                'assignment_number',
            ],

            ErpResource::WorkOrders => [
                'order_number',
                'work_order_number',
            ],

            ErpResource::Batches => [
                'batch_number',
                'batch_code',
                'lot_number',
            ],

            ErpResource::MachineRuns => [
                'record_number',
                'run_number',
            ],

            ErpResource::RunLogs => [
                'log_number',
            ],

            ErpResource::DowntimeEvents,
            ErpResource::MachineStatusEvents => [
                'event_number',
            ],

            ErpResource::MaintenanceHistory => [
                'maintenance_number',
            ],

            ErpResource::Inspections => [
                'inspection_number',
            ],

            ErpResource::Nonconformities => [
                'nonconformity_number',
                'nc_number',
            ],

            ErpResource::FinishedLots => [
                'lot_number',
            ],
        };
    }

    /**
     * Source data key => possible local database columns.
     *
     * @return array<string, list<string>>
     */
    public function aliases(
        ErpResource $resource
    ): array {
        $common = [
            'designation' => [
                'name',
                'designation',
            ],

            'base_unit' => [
                'base_unit',
                'quantity_unit',
                'unit',
            ],

            'quantity_unit' => [
                'quantity_unit',
                'base_unit',
                'unit',
            ],

            'unit' => [
                'unit',
                'quantity_unit',
                'base_unit',
            ],

            'enabled' => [
                'is_active',
                'enabled',
            ],

            'employee_code' => [
                'employee_code',
                'employee_number',
            ],

            'employee_number' => [
                'employee_number',
                'employee_code',
            ],

            'source_checksum' => [
                'source_checksum',
            ],
        ];

        $resourceAliases = match ($resource) {
            ErpResource::Machines => [
                'model_reference' => [
                    'model',
                    'model_reference',
                ],

                'sequence_order' => [
                    'sequence_number',
                    'sequence_order',
                ],
            ],

            ErpResource::Shifts => [
                'start_time' => [
                    'starts_at',
                    'start_time',
                ],

                'end_time' => [
                    'ends_at',
                    'end_time',
                ],
            ],

            ErpResource::Operators => [
                'hire_date' => [
                    'hired_on',
                    'hire_date',
                ],
            ],

            ErpResource::OperatorAssignments => [
                'valid_from' => [
                    'starts_on',
                    'valid_from',
                ],

                'valid_until' => [
                    'ends_on',
                    'valid_until',
                ],
            ],

            ErpResource::WorkOrders => [
                'work_order_number' => [
                    'work_order_number',
                    'order_number',
                ],

                'order_number' => [
                    'order_number',
                    'work_order_number',
                ],

                'planned_quantity' => [
                    'planned_quantity',
                    'target_quantity',
                ],

                'target_quantity' => [
                    'target_quantity',
                    'planned_quantity',
                ],

                'scheduled_start_at' => [
                    'scheduled_start_at',
                    'planned_start_at',
                ],

                'planned_start_at' => [
                    'planned_start_at',
                    'scheduled_start_at',
                ],

                'scheduled_end_at' => [
                    'scheduled_end_at',
                    'planned_end_at',
                ],

                'planned_end_at' => [
                    'planned_end_at',
                    'scheduled_end_at',
                ],
            ],

            ErpResource::Batches => [
                'batch_code' => [
                    'batch_code',
                    'batch_number',
                ],

                'batch_number' => [
                    'batch_number',
                    'batch_code',
                ],

                'planned_quantity' => [
                    'planned_quantity',
                    'target_quantity',
                ],

                'target_quantity' => [
                    'target_quantity',
                    'planned_quantity',
                ],
            ],

            ErpResource::MachineRuns => [
                'run_number' => [
                    'run_number',
                    'record_number',
                ],

                'record_number' => [
                    'record_number',
                    'run_number',
                ],

                'good_quantity' => [
                    'good_quantity',
                    'accepted_quantity',
                ],

                'rejected_quantity' => [
                    'rejected_quantity',
                    'scrap_quantity',
                ],

                'recorded_at' => [
                    'recorded_at',
                    'production_date',
                    'started_at',
                ],
            ],

            ErpResource::DowntimeEvents => [
                'occurred_at' => [
                    'occurred_at',
                    'started_at',
                ],

                'event_type' => [
                    'event_type',
                    'type',
                ],

                'reason' => [
                    'reason',
                    'description',
                ],
            ],

            ErpResource::MachineStatusEvents => [
                'occurred_at' => [
                    'occurred_at',
                    'started_at',
                ],
            ],

            ErpResource::MaintenanceHistory => [
                'performed_by_external_id' => [
                    'performed_by_external_id',
                ],
            ],

            ErpResource::Inspections => [
                'inspection_date' => [
                    'inspection_date',
                    'inspected_at',
                ],

                'inspected_at' => [
                    'inspected_at',
                    'inspection_date',
                ],
            ],

            ErpResource::Nonconformities => [
                'nc_number' => [
                    'nc_number',
                    'nonconformity_number',
                ],
            ],

            default => [],
        };

        return array_replace(
            $common,
            $resourceAliases
        );
    }

    /**
     * @return list<array{
     *     source_keys: list<string>,
     *     target_columns: list<string>,
     *     target_resource: ErpResource,
     *     required: bool
     * }>
     */
    public function relationships(
        ErpResource $resource
    ): array {
        return match ($resource) {
            ErpResource::Products => [
                $this->relationship(
                    sourceKeys: [
                        'product_family_external_id',
                        'family_external_id',
                    ],

                    targetColumns: [
                        'product_family_id',
                    ],

                    targetResource:
                        ErpResource::ProductFamilies,

                    required: true
                ),
            ],

            ErpResource::Machines => [
                $this->relationship(
                    sourceKeys: [
                        'production_line_external_id',
                        'line_external_id',
                    ],

                    targetColumns: [
                        'production_line_id',
                    ],

                    targetResource:
                        ErpResource::ProductionLines,

                    required: true
                ),
            ],

            ErpResource::OperatorAssignments => [
                $this->relationship(
                    ['operator_external_id'],
                    ['operator_id'],
                    ErpResource::Operators,
                    true
                ),

                $this->relationship(
                    [
                        'production_line_external_id',
                        'line_external_id',
                    ],
                    ['production_line_id'],
                    ErpResource::ProductionLines,
                    true
                ),

                $this->relationship(
                    ['shift_external_id'],
                    ['shift_id'],
                    ErpResource::Shifts,
                    true
                ),
            ],

            ErpResource::WorkOrders => [
                $this->relationship(
                    ['product_external_id'],
                    ['product_id'],
                    ErpResource::Products,
                    true
                ),

                $this->relationship(
                    [
                        'production_line_external_id',
                        'line_external_id',
                    ],
                    ['production_line_id'],
                    ErpResource::ProductionLines,
                    false
                ),

                $this->relationship(
                    ['shift_external_id'],
                    ['shift_id'],
                    ErpResource::Shifts,
                    false
                ),
            ],

            ErpResource::Batches => [
                $this->relationship(
                    [
                        'work_order_external_id',
                        'production_order_external_id',
                    ],
                    [
                        'production_order_id',
                        'work_order_id',
                    ],
                    ErpResource::WorkOrders,
                    true
                ),

                $this->relationship(
                    ['product_external_id'],
                    ['product_id'],
                    ErpResource::Products,
                    false
                ),

                $this->relationship(
                    [
                        'production_line_external_id',
                        'line_external_id',
                    ],
                    ['production_line_id'],
                    ErpResource::ProductionLines,
                    false
                ),

                $this->relationship(
                    ['shift_external_id'],
                    ['shift_id'],
                    ErpResource::Shifts,
                    false
                ),
            ],

            ErpResource::MachineRuns => [
                $this->relationship(
                    [
                        'batch_external_id',
                        'production_batch_external_id',
                    ],
                    [
                        'production_batch_id',
                        'batch_id',
                    ],
                    ErpResource::Batches,
                    true
                ),

                $this->relationship(
                    [
                        'production_line_external_id',
                        'line_external_id',
                    ],
                    [
                        'production_line_id',
                    ],
                    ErpResource::ProductionLines,
                    true
                ),

                $this->relationship(
                    ['shift_external_id'],
                    ['shift_id'],
                    ErpResource::Shifts,
                    true
                ),

                $this->relationship(
                    ['operator_external_id'],
                    ['operator_id'],
                    ErpResource::Operators,
                    false
                ),
            ],

            ErpResource::RunLogs => [
                $this->relationship(
                    [
                        'machine_run_external_id',
                        'production_record_external_id',
                    ],
                    [
                        'production_record_id',
                        'machine_run_id',
                    ],
                    ErpResource::MachineRuns,
                    false
                ),

                $this->relationship(
                    ['machine_external_id'],
                    ['machine_id'],
                    ErpResource::Machines,
                    false
                ),
            ],

            ErpResource::DowntimeEvents => [
                $this->relationship(
                    [
                        'batch_external_id',
                        'production_batch_external_id',
                    ],
                    [
                        'production_batch_id',
                        'batch_id',
                    ],
                    ErpResource::Batches,
                    true
                ),

                $this->relationship(
                    [
                        'production_line_external_id',
                        'line_external_id',
                    ],
                    [
                        'production_line_id',
                    ],
                    ErpResource::ProductionLines,
                    true
                ),

                $this->relationship(
                    ['machine_external_id'],
                    ['machine_id'],
                    ErpResource::Machines,
                    true
                ),

                $this->relationship(
                    ['shift_external_id'],
                    ['shift_id'],
                    ErpResource::Shifts,
                    false
                ),

                $this->relationship(
                    [
                        'machine_run_external_id',
                        'production_record_external_id',
                    ],
                    [
                        'production_record_id',
                        'machine_run_id',
                    ],
                    ErpResource::MachineRuns,
                    false
                ),

                $this->relationship(
                    ['operator_external_id'],
                    ['operator_id'],
                    ErpResource::Operators,
                    false
                ),
            ],

            ErpResource::MachineStatusEvents => [
                $this->relationship(
                    ['machine_external_id'],
                    ['machine_id'],
                    ErpResource::Machines,
                    true
                ),
            ],

            ErpResource::MaintenanceHistory => [
                $this->relationship(
                    ['machine_external_id'],
                    ['machine_id'],
                    ErpResource::Machines,
                    true
                ),

                $this->relationship(
                    [
                        'performed_by_external_id',
                        'operator_external_id',
                    ],
                    [
                        'operator_id',
                        'performed_by_operator_id',
                    ],
                    ErpResource::Operators,
                    false
                ),
            ],

            ErpResource::Inspections => [
                $this->relationship(
                    [
                        'batch_external_id',
                        'production_batch_external_id',
                    ],
                    [
                        'production_batch_id',
                        'batch_id',
                    ],
                    ErpResource::Batches,
                    true
                ),

                $this->relationship(
                    [
                        'inspector_external_id',
                        'operator_external_id',
                    ],
                    [
                        'inspector_operator_id',
                        'operator_id',
                    ],
                    ErpResource::Operators,
                    false
                ),

                $this->relationship(
                    ['finished_lot_external_id'],
                    ['finished_lot_id'],
                    ErpResource::FinishedLots,
                    false
                ),
            ],

            ErpResource::Nonconformities => [
                $this->relationship(
                    ['inspection_external_id'],
                    ['inspection_id'],
                    ErpResource::Inspections,
                    true
                ),

                $this->relationship(
                    [
                        'batch_external_id',
                        'production_batch_external_id',
                    ],
                    [
                        'production_batch_id',
                        'batch_id',
                    ],
                    ErpResource::Batches,
                    false
                ),
            ],

            ErpResource::FinishedLots => [
                $this->relationship(
                    [
                        'batch_external_id',
                        'production_batch_external_id',
                    ],
                    [
                        'production_batch_id',
                        'batch_id',
                    ],
                    ErpResource::Batches,
                    true
                ),

                $this->relationship(
                    ['product_external_id'],
                    ['product_id'],
                    ErpResource::Products,
                    true
                ),

                $this->relationship(
                    [
                        'released_by_external_id',
                        'operator_external_id',
                    ],
                    [
                        'released_by_operator_id',
                        'operator_id',
                    ],
                    ErpResource::Operators,
                    false
                ),
            ],

            default => [],
        };
    }

    /**
     * @param list<string> $sourceKeys
     * @param list<string> $targetColumns
     *
     * @return array{
     *     source_keys: list<string>,
     *     target_columns: list<string>,
     *     target_resource: ErpResource,
     *     required: bool
     * }
     */
    private function relationship(
        array $sourceKeys,
        array $targetColumns,
        ErpResource $targetResource,
        bool $required
    ): array {
        return [
            'source_keys' => $sourceKeys,
            'target_columns' => $targetColumns,
            'target_resource' => $targetResource,
            'required' => $required,
        ];
    }
}