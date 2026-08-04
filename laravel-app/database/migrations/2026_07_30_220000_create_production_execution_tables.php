<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the DSS production-execution structure.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Production orders
        |--------------------------------------------------------------------------
        |
        | Orders may originate from the simulated Sage connector or be
        | created manually by an authorized DSS user.
        |
        */

        Schema::create(
            'production_orders',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'order_number',
                    100
                )->unique();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId(
                    'production_line_id'
                )
                    ->constrained('production_lines')
                    ->restrictOnDelete();

                $table->foreignId('shift_id')
                    ->nullable()
                    ->constrained('shifts')
                    ->restrictOnDelete();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'planned_start_at'
                );

                $table->timestamp(
                    'planned_end_at'
                )->nullable();

                $table->decimal(
                    'target_quantity',
                    16,
                    3
                );

                $table->string(
                    'quantity_unit',
                    30
                )->default('bottles');

                /*
                 * Expected values:
                 * draft, planned, released, in_progress,
                 * completed, cancelled.
                 */
                $table->string(
                    'status',
                    30
                )->default('planned');

                /*
                 * 1 = highest priority.
                 * 5 = lowest priority.
                 */
                $table->unsignedTinyInteger(
                    'priority'
                )->default(3);

                $table->text('instructions')
                    ->nullable();

                /*
                 * Incremented during concurrent updates.
                 */
                $table->unsignedInteger(
                    'lock_version'
                )->default(1);

                $this->addSourceMetadata(
                    $table,
                    'production_orders'
                );

                $table->timestamps();

                $table->index(
                    [
                        'status',
                        'planned_start_at',
                    ],
                    'prod_orders_status_start_index'
                );

                $table->index(
                    [
                        'product_id',
                        'production_line_id',
                        'status',
                    ],
                    'prod_orders_product_line_index'
                );

                $table->index(
                    [
                        'production_line_id',
                        'shift_id',
                        'planned_start_at',
                    ],
                    'prod_orders_line_shift_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Production batches
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'production_batches',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'production_order_id'
                )
                    ->constrained('production_orders')
                    ->restrictOnDelete();

                $table->string(
                    'batch_number',
                    100
                )->unique();

                $table->unsignedSmallInteger(
                    'sequence_number'
                )->default(1);

                /*
                 * Expected values:
                 * planned, ready, in_progress, completed,
                 * blocked, cancelled.
                 */
                $table->string(
                    'status',
                    30
                )->default('planned');

                $table->decimal(
                    'planned_quantity',
                    16,
                    3
                );

                $table->decimal(
                    'actual_good_quantity',
                    16,
                    3
                )->default(0);

                $table->decimal(
                    'actual_rejected_quantity',
                    16,
                    3
                )->default(0);

                $table->string(
                    'quantity_unit',
                    30
                )->default('bottles');

                $table->timestamp(
                    'scheduled_start_at'
                )->nullable();

                $table->timestamp(
                    'actual_start_at'
                )->nullable();

                $table->timestamp(
                    'actual_end_at'
                )->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->unsignedInteger(
                    'lock_version'
                )->default(1);

                $this->addSourceMetadata(
                    $table,
                    'production_batches'
                );

                $table->timestamps();

                $table->unique(
                    [
                        'production_order_id',
                        'sequence_number',
                    ],
                    'prod_batches_order_sequence_unique'
                );

                $table->index(
                    [
                        'production_order_id',
                        'status',
                    ],
                    'prod_batches_order_status_index'
                );

                $table->index(
                    [
                        'status',
                        'scheduled_start_at',
                    ],
                    'prod_batches_status_start_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Production records
        |--------------------------------------------------------------------------
        |
        | These records capture production quantities and operating time.
        | The employee operator and the authenticated DSS user are stored
        | separately for traceability.
        |
        */

        Schema::create(
            'production_records',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'record_number',
                    100
                )->unique();

                $table->foreignId(
                    'production_batch_id'
                )
                    ->constrained('production_batches')
                    ->restrictOnDelete();

                $table->foreignId(
                    'production_line_id'
                )
                    ->constrained('production_lines')
                    ->restrictOnDelete();

                $table->foreignId('shift_id')
                    ->constrained('shifts')
                    ->restrictOnDelete();

                $table->foreignId('operator_id')
                    ->nullable()
                    ->constrained('operators')
                    ->restrictOnDelete();

                $table->foreignId('recorded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->date('production_date');

                $table->timestamp('started_at')
                    ->nullable();

                $table->timestamp('ended_at')
                    ->nullable();

                $table->decimal(
                    'produced_quantity',
                    16,
                    3
                )->default(0);

                $table->decimal(
                    'good_quantity',
                    16,
                    3
                )->default(0);

                $table->decimal(
                    'rejected_quantity',
                    16,
                    3
                )->default(0);

                $table->string(
                    'quantity_unit',
                    30
                )->default('bottles');

                $table->unsignedInteger(
                    'runtime_minutes'
                )->default(0);

                $table->unsignedInteger(
                    'downtime_minutes'
                )->default(0);

                /*
                 * Expected lifecycle values:
                 * draft, submitted, locked.
                 */
                $table->string(
                    'status',
                    30
                )->default('draft');

                /*
                 * Expected decision values:
                 * pending, validated, rejected.
                 */
                $table->string(
                    'validation_status',
                    30
                )->default('pending');

                $table->timestamp('submitted_at')
                    ->nullable();

                $table->timestamp('locked_at')
                    ->nullable();

                $table->text('notes')
                    ->nullable();

                $table->unsignedInteger(
                    'lock_version'
                )->default(1);

                $this->addSourceMetadata(
                    $table,
                    'production_records'
                );

                $table->timestamps();

                $table->index(
                    [
                        'production_batch_id',
                        'status',
                    ],
                    'prod_records_batch_status_index'
                );

                $table->index(
                    [
                        'production_line_id',
                        'shift_id',
                        'production_date',
                    ],
                    'prod_records_line_shift_date_index'
                );

                $table->index(
                    [
                        'operator_id',
                        'production_date',
                    ],
                    'prod_records_operator_date_index'
                );

                $table->index(
                    [
                        'validation_status',
                        'submitted_at',
                    ],
                    'prod_records_validation_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Production-record validation history
        |--------------------------------------------------------------------------
        |
        | Every supervisor decision is stored as a separate historical row.
        | Decisions are not overwritten.
        |
        */

        Schema::create(
            'production_record_validations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'production_record_id'
                )
                    ->constrained('production_records')
                    ->restrictOnDelete();

                $table->foreignId('decided_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                /*
                 * Expected values:
                 * validated, rejected.
                 */
                $table->string(
                    'decision',
                    30
                );

                /*
                 * Production-record version reviewed by the supervisor.
                 */
                $table->unsignedInteger(
                    'record_version'
                );

                $table->text(
                    'decision_reason'
                )->nullable();

                $table->timestamp(
                    'decided_at'
                );

                /*
                 * Correlates a decision with the HTTP audit request.
                 */
                $table->string(
                    'request_id',
                    64
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'production_record_id',
                        'record_version',
                    ],
                    'prod_validations_record_version_unique'
                );

                $table->index(
                    [
                        'decision',
                        'decided_at',
                    ],
                    'prod_validations_decision_date_index'
                );

                $table->index(
                    [
                        'decided_by',
                        'decided_at',
                    ],
                    'prod_validations_user_date_index'
                );

                $table->index(
                    'request_id',
                    'prod_validations_request_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Production events
        |--------------------------------------------------------------------------
        |
        | Events support production observations, downtime reports,
        | machine incidents, quality observations, and comments.
        |
        */

        Schema::create(
            'production_events',
            function (Blueprint $table): void {
                $table->id();

                $table->string(
                    'event_number',
                    100
                )->unique();

                $table->foreignId(
                    'production_batch_id'
                )
                    ->constrained('production_batches')
                    ->restrictOnDelete();

                $table->foreignId(
                    'production_record_id'
                )
                    ->nullable()
                    ->constrained('production_records')
                    ->restrictOnDelete();

                $table->foreignId(
                    'production_line_id'
                )
                    ->constrained('production_lines')
                    ->restrictOnDelete();

                $table->foreignId('machine_id')
                    ->nullable()
                    ->constrained('machines')
                    ->restrictOnDelete();

                $table->foreignId('shift_id')
                    ->nullable()
                    ->constrained('shifts')
                    ->restrictOnDelete();

                $table->foreignId('operator_id')
                    ->nullable()
                    ->constrained('operators')
                    ->restrictOnDelete();

                $table->foreignId('reported_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('resolved_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                 * Expected values:
                 * production, downtime, machine_incident,
                 * quality, comment.
                 */
                $table->string(
                    'event_type',
                    40
                );

                /*
                 * Expected values:
                 * information, warning, critical.
                 */
                $table->string(
                    'severity',
                    20
                )->default('information');

                $table->string(
                    'title',
                    180
                );

                $table->text('description')
                    ->nullable();

                $table->timestamp('started_at');

                $table->timestamp('ended_at')
                    ->nullable();

                $table->unsignedInteger(
                    'duration_minutes'
                )->nullable();

                $table->boolean(
                    'is_resolved'
                )->default(false);

                $table->timestamp(
                    'resolved_at'
                )->nullable();

                $table->unsignedInteger(
                    'lock_version'
                )->default(1);

                $this->addSourceMetadata(
                    $table,
                    'production_events'
                );

                $table->timestamps();

                $table->index(
                    [
                        'event_type',
                        'severity',
                        'started_at',
                    ],
                    'prod_events_type_severity_index'
                );

                $table->index(
                    [
                        'production_line_id',
                        'started_at',
                    ],
                    'prod_events_line_start_index'
                );

                $table->index(
                    [
                        'machine_id',
                        'started_at',
                    ],
                    'prod_events_machine_start_index'
                );

                $table->index(
                    [
                        'production_batch_id',
                        'started_at',
                    ],
                    'prod_events_batch_start_index'
                );

                $table->index(
                    [
                        'is_resolved',
                        'severity',
                    ],
                    'prod_events_resolution_index'
                );
            }
        );
    }

    /**
     * Drop the tables in reverse dependency order.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'production_events'
        );

        Schema::dropIfExists(
            'production_record_validations'
        );

        Schema::dropIfExists(
            'production_records'
        );

        Schema::dropIfExists(
            'production_batches'
        );

        Schema::dropIfExists(
            'production_orders'
        );
    }

    /**
     * Add ERP/source synchronization traceability.
     */
    private function addSourceMetadata(
        Blueprint $table,
        string $indexPrefix
    ): void {
        $table->string(
            'source_system',
            50
        )->default('manual');

        $table->string(
            'external_id',
            120
        )->nullable();

        $table->unsignedBigInteger(
            'source_version'
        )->nullable();

        $table->char(
            'source_checksum',
            64
        )->nullable();

        $table->timestamp(
            'source_updated_at'
        )->nullable();

        $table->timestamp(
            'last_synced_at'
        )->nullable();

        /*
         * Expected values:
         * not_applicable, pending, imported, skipped, failed.
         */
        $table->string(
            'import_status',
            30
        )->default('not_applicable');

        $table->text(
            'import_error'
        )->nullable();

        $table->unique(
            [
                'source_system',
                'external_id',
            ],
            $indexPrefix
            .'_source_external_unique'
        );

        $table->index(
            [
                'source_system',
                'import_status',
                'last_synced_at',
            ],
            $indexPrefix
            .'_source_sync_index'
        );
    }
};