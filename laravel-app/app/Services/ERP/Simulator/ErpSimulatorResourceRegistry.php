<?php

namespace App\Services\ERP\Simulator;

use App\Enums\ERP\ErpResource;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ErpSimulatorResourceRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private array $columnCache = [];

    /**
     * @return array{
     *     tables: list<string>,
     *     external_id_columns: list<string>,
     *     date_columns: list<string>
     * }
     */
    public function definition(
        ErpResource $resource
    ): array {
        return match ($resource) {
            ErpResource::ProductFamilies => [
                'tables' => [
                    'product_families',
                ],

                'external_id_columns' => [
                    'external_id',
                    'code',
                    'id',
                ],

                'date_columns' => [
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Products => [
                'tables' => [
                    'products',
                ],

                'external_id_columns' => [
                    'external_id',
                    'code',
                    'sku',
                    'id',
                ],

                'date_columns' => [
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::ProductionLines => [
                'tables' => [
                    'production_lines',
                ],

                'external_id_columns' => [
                    'external_id',
                    'code',
                    'id',
                ],

                'date_columns' => [
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Machines => [
                'tables' => [
                    'machines',
                ],

                'external_id_columns' => [
                    'external_id',
                    'code',
                    'id',
                ],

                'date_columns' => [
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Shifts => [
                'tables' => [
                    'shifts',
                ],

                'external_id_columns' => [
                    'external_id',
                    'code',
                    'id',
                ],

                'date_columns' => [
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Operators => [
                'tables' => [
                    'operators',
                ],

                'external_id_columns' => [
                    'external_id',
                    'employee_number',
                    'code',
                    'id',
                ],

                'date_columns' => [
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::OperatorAssignments => [
                'tables' => [
                    'operator_assignments',
                ],

                'external_id_columns' => [
                    'external_id',
                    'assignment_number',
                    'id',
                ],

                'date_columns' => [
                    'valid_from',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::WorkOrders => [
                'tables' => [
                    'work_orders',
                    'production_orders',
                ],

                'external_id_columns' => [
                    'external_id',
                    'order_number',
                    'work_order_number',
                    'id',
                ],

                'date_columns' => [
                    'planned_start_at',
                    'scheduled_start_at',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Batches => [
                'tables' => [
                    'batches',
                    'production_batches',
                ],

                'external_id_columns' => [
                    'external_id',
                    'batch_number',
                    'lot_number',
                    'id',
                ],

                'date_columns' => [
                    'scheduled_start_at',
                    'actual_start_at',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::MachineRuns => [
                'tables' => [
                    'machine_runs',
                    'production_records',
                ],

                'external_id_columns' => [
                    'external_id',
                    'run_number',
                    'record_number',
                    'id',
                ],

                'date_columns' => [
                    'started_at',
                    'production_date',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::RunLogs => [
                'tables' => [
                    'run_logs',
                ],

                'external_id_columns' => [
                    'external_id',
                    'log_number',
                    'id',
                ],

                'date_columns' => [
                    'recorded_at',
                    'logged_at',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::DowntimeEvents => [
                'tables' => [
                    'downtime_events',
                    'production_events',
                ],

                'external_id_columns' => [
                    'external_id',
                    'event_number',
                    'id',
                ],

                'date_columns' => [
                    'started_at',
                    'occurred_at',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::MachineStatusEvents => [
                'tables' => [
                    'machine_status_events',
                ],

                'external_id_columns' => [
                    'external_id',
                    'event_number',
                    'id',
                ],

                'date_columns' => [
                    'occurred_at',
                    'started_at',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::MaintenanceHistory => [
                'tables' => [
                    'maintenance_history',
                ],

                'external_id_columns' => [
                    'external_id',
                    'maintenance_number',
                    'id',
                ],

                'date_columns' => [
                    'scheduled_at',
                    'started_at',
                    'completed_at',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Inspections => [
                'tables' => [
                    'inspections',
                ],

                'external_id_columns' => [
                    'external_id',
                    'inspection_number',
                    'id',
                ],

                'date_columns' => [
                    'inspected_at',
                    'inspection_date',
                    'updated_at',
                    'created_at',
                ],
            ],

            ErpResource::Nonconformities => [
                'tables' => [
                    'nonconformities',
                ],

                'external_id_columns' => [
                    'external_id',
                    'nonconformity_number',
                    'nc_number',
                    'id',
                ],

                'date_columns' => [
                    'detected_at',
                    'created_at',
                    'updated_at',
                ],
            ],

            ErpResource::FinishedLots => [
                'tables' => [
                    'finished_lots',
                ],

                'external_id_columns' => [
                    'external_id',
                    'lot_number',
                    'id',
                ],

                'date_columns' => [
                    'produced_at',
                    'released_at',
                    'updated_at',
                    'created_at',
                ],
            ],
        };
    }

    public function tableFor(
        ErpResource $resource
    ): string {
        $definition = $this->definition(
            $resource
        );

        foreach (
            $definition['tables']
            as $table
        ) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        throw new RuntimeException(
            'No simulator table is available for resource ['
            .$resource->value
            .'].'
        );
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
                Schema::getColumnListing(
                    $table
                );
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
     * @return array<string, bool>
     */
    public function availability(): array
    {
        $availability = [];

        foreach (
            ErpResource::cases()
            as $resource
        ) {
            try {
                $this->tableFor($resource);

                $availability[
                    $resource->value
                ] = true;
            } catch (RuntimeException) {
                $availability[
                    $resource->value
                ] = false;
            }
        }

        return $availability;
    }
}