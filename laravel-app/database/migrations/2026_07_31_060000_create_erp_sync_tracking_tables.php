<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'erp_sync_runs',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->uuid('run_uuid')
                    ->unique();

                $table
                    ->string('source_system', 80)
                    ->index();

                $table
                    ->string('trigger', 30)
                    ->index();

                $table
                    ->string('status', 40)
                    ->index();

                $table
                    ->foreignId('initiated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->string('request_id', 100)
                    ->nullable()
                    ->index();

                /*
                 * A safe list of requested ErpResource values.
                 * No connector token or payload is stored here.
                 */
                $table->json('requested_resources');

                $table
                    ->unsignedBigInteger('pages_processed')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_fetched')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_mapped')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_created')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_updated')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_skipped')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_failed')
                    ->default(0);

                $table
                    ->string('error_code', 100)
                    ->nullable();

                $table
                    ->text('error_message')
                    ->nullable();

                $table
                    ->timestamp('started_at')
                    ->nullable()
                    ->index();

                $table
                    ->timestamp('finished_at')
                    ->nullable()
                    ->index();

                $table->timestamps();

                $table->index(
                    [
                        'source_system',
                        'status',
                        'started_at',
                    ],
                    'erp_sync_runs_source_status_idx'
                );
            }
        );

        Schema::create(
            'erp_sync_run_resources',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('erp_sync_run_id')
                    ->constrained('erp_sync_runs')
                    ->cascadeOnDelete();

                $table
                    ->string('resource', 80)
                    ->index();

                $table
                    ->string('status', 40)
                    ->index();

                $table
                    ->unsignedBigInteger('pages_processed')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_fetched')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_mapped')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_created')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_updated')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_skipped')
                    ->default(0);

                $table
                    ->unsignedBigInteger('records_failed')
                    ->default(0);

                $table
                    ->timestamp('last_source_updated_at')
                    ->nullable();

                $table
                    ->unsignedBigInteger('last_source_version')
                    ->nullable();

                /*
                 * Only a SHA-256 fingerprint is stored here.
                 * The opaque cursor itself belongs in erp_sync_states.
                 */
                $table
                    ->string(
                        'last_cursor_fingerprint',
                        64
                    )
                    ->nullable();

                $table
                    ->string('error_code', 100)
                    ->nullable();

                $table
                    ->text('error_message')
                    ->nullable();

                $table
                    ->timestamp('started_at')
                    ->nullable();

                $table
                    ->timestamp('finished_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'erp_sync_run_id',
                        'resource',
                    ],
                    'erp_sync_run_resource_unique'
                );

                $table->index(
                    [
                        'resource',
                        'status',
                    ],
                    'erp_sync_resource_status_idx'
                );
            }
        );

        Schema::create(
            'erp_sync_states',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('source_system', 80);

                $table
                    ->string('resource', 80);

                $table
                    ->timestamp('last_successful_sync_at')
                    ->nullable();

                $table
                    ->timestamp('last_source_updated_at')
                    ->nullable();

                $table
                    ->unsignedBigInteger('last_source_version')
                    ->nullable();

                /*
                 * The next page to request after a partial run.
                 */
                $table
                    ->unsignedInteger('resume_page')
                    ->default(1);

                /*
                 * This value is encrypted through the Eloquent model.
                 * TEXT is required because encrypted output length varies.
                 */
                $table
                    ->text('resume_cursor')
                    ->nullable();

                $table
                    ->string(
                        'resume_cursor_fingerprint',
                        64
                    )
                    ->nullable();

                $table
                    ->foreignId('last_run_id')
                    ->nullable()
                    ->constrained('erp_sync_runs')
                    ->nullOnDelete();

                /*
                 * Database-backed worker lease fields.
                 */
                $table
                    ->string('lock_owner', 100)
                    ->nullable();

                $table
                    ->timestamp('lock_acquired_at')
                    ->nullable()
                    ->index();

                $table
                    ->unsignedInteger('consecutive_failures')
                    ->default(0);

                $table
                    ->string('last_error_code', 100)
                    ->nullable();

                $table
                    ->text('last_error_message')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'source_system',
                        'resource',
                    ],
                    'erp_sync_state_source_resource_unique'
                );

                $table->index(
                    [
                        'source_system',
                        'lock_acquired_at',
                    ],
                    'erp_sync_state_lock_idx'
                );
            }
        );

        Schema::create(
            'erp_sync_failures',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('erp_sync_run_id')
                    ->constrained('erp_sync_runs')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('erp_sync_run_resource_id')
                    ->nullable()
                    ->constrained('erp_sync_run_resources')
                    ->nullOnDelete();

                $table
                    ->string('resource', 80)
                    ->index();

                $table
                    ->string('stage', 40)
                    ->index();

                /*
                 * Optional ERP business identifier.
                 * Payloads are never stored in this table.
                 */
                $table
                    ->string('external_id', 120)
                    ->nullable()
                    ->index();

                $table
                    ->unsignedInteger('page')
                    ->nullable();

                $table
                    ->string('cursor_fingerprint', 64)
                    ->nullable();

                $table
                    ->string('error_code', 100)
                    ->index();

                $table->text('error_message');

                $table
                    ->boolean('retryable')
                    ->default(false)
                    ->index();

                /*
                 * Only redacted, explicitly safe context is allowed.
                 */
                $table
                    ->json('safe_context')
                    ->nullable();

                $table
                    ->timestamp('occurred_at')
                    ->index();

                $table->timestamps();

                $table->index(
                    [
                        'erp_sync_run_id',
                        'resource',
                    ],
                    'erp_sync_failures_run_resource_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'erp_sync_failures'
        );

        Schema::dropIfExists(
            'erp_sync_states'
        );

        Schema::dropIfExists(
            'erp_sync_run_resources'
        );

        Schema::dropIfExists(
            'erp_sync_runs'
        );
    }
};