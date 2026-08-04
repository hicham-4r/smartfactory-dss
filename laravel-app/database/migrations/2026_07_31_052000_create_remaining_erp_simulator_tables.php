<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'run_logs',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Stable identifier supplied by the simulated ERP.
                 */
                $table
                    ->string('external_id', 120)
                    ->unique();

                $table
                    ->string(
                        'machine_run_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string(
                        'machine_external_id',
                        120
                    )
                    ->nullable()
                    ->index();

                $table
                    ->string('log_type', 50)
                    ->index();

                $table->text('message');

                $table
                    ->timestamp('recorded_at')
                    ->index();

                $table
                    ->decimal(
                        'numeric_value',
                        18,
                        3
                    )
                    ->nullable();

                $table
                    ->string('unit', 30)
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_version'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'source_updated_at'
                    )
                    ->useCurrent();

                $table->timestamps();

                $table->index(
                    [
                        'machine_run_external_id',
                        'recorded_at',
                    ],
                    'run_logs_run_recorded_idx'
                );

                $table->index(
                    [
                        'source_version',
                        'source_updated_at',
                    ],
                    'run_logs_source_sync_idx'
                );
            }
        );

        Schema::create(
            'machine_status_events',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('external_id', 120)
                    ->unique();

                $table
                    ->string(
                        'machine_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string('status', 50)
                    ->index();

                $table
                    ->timestamp('occurred_at')
                    ->index();

                $table
                    ->timestamp('ended_at')
                    ->nullable();

                $table
                    ->text('reason')
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_version'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'source_updated_at'
                    )
                    ->useCurrent();

                $table->timestamps();

                $table->index(
                    [
                        'machine_external_id',
                        'occurred_at',
                    ],
                    'machine_status_machine_time_idx'
                );

                $table->index(
                    [
                        'source_version',
                        'source_updated_at',
                    ],
                    'machine_status_source_sync_idx'
                );
            }
        );

        Schema::create(
            'maintenance_history',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('external_id', 120)
                    ->unique();

                $table
                    ->string(
                        'maintenance_number',
                        120
                    )
                    ->unique();

                $table
                    ->string(
                        'machine_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string(
                        'maintenance_type',
                        50
                    )
                    ->index();

                $table
                    ->string('status', 50)
                    ->index();

                $table
                    ->timestamp('scheduled_at')
                    ->nullable()
                    ->index();

                $table
                    ->timestamp('started_at')
                    ->nullable();

                $table
                    ->timestamp('completed_at')
                    ->nullable();

                $table
                    ->string(
                        'performed_by_external_id',
                        120
                    )
                    ->nullable()
                    ->index();

                $table
                    ->text('description')
                    ->nullable();

                $table
                    ->text('actions_taken')
                    ->nullable();

                $table
                    ->unsignedInteger(
                        'downtime_minutes'
                    )
                    ->default(0);

                $table
                    ->decimal('cost', 15, 2)
                    ->nullable();

                $table
                    ->string('currency', 3)
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_version'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'source_updated_at'
                    )
                    ->useCurrent();

                $table->timestamps();

                $table->index(
                    [
                        'machine_external_id',
                        'status',
                    ],
                    'maintenance_machine_status_idx'
                );

                $table->index(
                    [
                        'source_version',
                        'source_updated_at',
                    ],
                    'maintenance_source_sync_idx'
                );
            }
        );

        Schema::create(
            'finished_lots',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('external_id', 120)
                    ->unique();

                $table
                    ->string('lot_number', 120)
                    ->unique();

                $table
                    ->string(
                        'batch_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string(
                        'product_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string('status', 50)
                    ->index();

                $table
                    ->timestamp('produced_at')
                    ->index();

                $table
                    ->date('expiry_date')
                    ->nullable()
                    ->index();

                $table->decimal(
                    'produced_quantity',
                    18,
                    3
                );

                $table
                    ->decimal(
                        'released_quantity',
                        18,
                        3
                    )
                    ->default(0);

                $table
                    ->decimal(
                        'rejected_quantity',
                        18,
                        3
                    )
                    ->default(0);

                $table
                    ->string(
                        'quantity_unit',
                        30
                    );

                $table
                    ->timestamp('released_at')
                    ->nullable();

                $table
                    ->string(
                        'released_by_external_id',
                        120
                    )
                    ->nullable()
                    ->index();

                $table
                    ->text('release_notes')
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_version'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'source_updated_at'
                    )
                    ->useCurrent();

                $table->timestamps();

                $table->index(
                    [
                        'batch_external_id',
                        'status',
                    ],
                    'finished_lots_batch_status_idx'
                );

                $table->index(
                    [
                        'source_version',
                        'source_updated_at',
                    ],
                    'finished_lots_source_sync_idx'
                );
            }
        );

        Schema::create(
            'inspections',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('external_id', 120)
                    ->unique();

                $table
                    ->string(
                        'inspection_number',
                        120
                    )
                    ->unique();

                $table
                    ->string(
                        'batch_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string(
                        'finished_lot_external_id',
                        120
                    )
                    ->nullable()
                    ->index();

                $table
                    ->string(
                        'inspector_external_id',
                        120
                    )
                    ->nullable()
                    ->index();

                $table
                    ->string(
                        'inspection_type',
                        120
                    )
                    ->index();

                $table
                    ->string('result', 50)
                    ->index();

                $table
                    ->timestamp('inspected_at')
                    ->index();

                $table
                    ->unsignedInteger('sample_size')
                    ->nullable();

                $table
                    ->unsignedInteger(
                        'passed_quantity'
                    )
                    ->nullable();

                $table
                    ->unsignedInteger(
                        'failed_quantity'
                    )
                    ->nullable();

                $table
                    ->text('notes')
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_version'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'source_updated_at'
                    )
                    ->useCurrent();

                $table->timestamps();

                $table->index(
                    [
                        'batch_external_id',
                        'inspected_at',
                    ],
                    'inspections_batch_time_idx'
                );

                $table->index(
                    [
                        'source_version',
                        'source_updated_at',
                    ],
                    'inspections_source_sync_idx'
                );
            }
        );

        Schema::create(
            'nonconformities',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('external_id', 120)
                    ->unique();

                $table
                    ->string(
                        'nonconformity_number',
                        120
                    )
                    ->unique();

                $table
                    ->string(
                        'inspection_external_id',
                        120
                    )
                    ->index();

                $table
                    ->string(
                        'batch_external_id',
                        120
                    )
                    ->nullable()
                    ->index();

                $table
                    ->string('severity', 50)
                    ->index();

                $table
                    ->string('status', 50)
                    ->index();

                $table
                    ->string('category', 120)
                    ->index();

                $table->text('description');

                $table
                    ->timestamp('detected_at')
                    ->index();

                $table
                    ->timestamp('corrected_at')
                    ->nullable();

                $table
                    ->text('corrective_action')
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'source_version'
                    )
                    ->default(1);

                $table
                    ->timestamp(
                        'source_updated_at'
                    )
                    ->useCurrent();

                $table->timestamps();

                $table->index(
                    [
                        'inspection_external_id',
                        'status',
                    ],
                    'nonconformities_inspection_status_idx'
                );

                $table->index(
                    [
                        'source_version',
                        'source_updated_at',
                    ],
                    'nonconformities_source_sync_idx'
                );
            }
        );
    }

    public function down(): void
    {
        /*
         * Drop dependants first, followed by their source records.
         */
        Schema::dropIfExists(
            'nonconformities'
        );

        Schema::dropIfExists(
            'inspections'
        );

        Schema::dropIfExists(
            'finished_lots'
        );

        Schema::dropIfExists(
            'maintenance_history'
        );

        Schema::dropIfExists(
            'machine_status_events'
        );

        Schema::dropIfExists(
            'run_logs'
        );
    }
};