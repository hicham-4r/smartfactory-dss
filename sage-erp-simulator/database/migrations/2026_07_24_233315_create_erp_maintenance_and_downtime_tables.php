<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_downtime_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('machine_id')
                ->constrained('erp_machines')
                ->restrictOnDelete();

            $table->foreignId('production_line_id')
                ->constrained('erp_production_lines')
                ->restrictOnDelete();

            $table->foreignId('production_batch_id')
                ->nullable()
                ->constrained('erp_production_batches')
                ->nullOnDelete();

            $table->foreignId('shift_id')
                ->nullable()
                ->constrained('erp_shifts')
                ->nullOnDelete();

            $table->string('event_number', 180)->unique();

            $table->string('category', 30);
            $table->string('downtime_type', 80);

            $table->string('reason_code', 80)->nullable();
            $table->text('reason_description')->nullable();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            $table->unsignedSmallInteger(
                'duration_minutes'
            )->default(0);

            $table->unsignedInteger(
                'production_impact_units'
            )->default(0);

            $table->string('status', 40)
                ->default('resolved');

            $table->boolean('is_late_arrival')
                ->default(false);

            $table->dateTime('source_updated_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['machine_id', 'started_at'],
                'downtime_machine_start_index'
            );

            $table->index(
                ['production_line_id', 'started_at'],
                'downtime_line_start_index'
            );

            $table->index(
                ['downtime_type', 'status'],
                'downtime_type_status_index'
            );

            $table->index(
                'source_updated_at',
                'downtime_source_updated_index'
            );
        });

        Schema::create(
            'erp_machine_status_events',
            function (Blueprint $table) {
                $table->id();
                $table->uuid('external_id')->unique();

                $table->foreignId('machine_id')
                    ->constrained('erp_machines')
                    ->restrictOnDelete();

                $table->foreignId('production_line_id')
                    ->constrained('erp_production_lines')
                    ->restrictOnDelete();

                $table->foreignId('shift_id')
                    ->nullable()
                    ->constrained('erp_shifts')
                    ->nullOnDelete();

                $table->string(
                    'status_event_number',
                    200
                )->unique();

                $table->string('status_code', 50);

                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();

                $table->unsignedSmallInteger(
                    'duration_minutes'
                )->nullable();

                $table->boolean('is_late_arrival')
                    ->default(false);

                $table->dateTime('source_updated_at')
                    ->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(
                    ['machine_id', 'started_at'],
                    'machine_status_start_unique'
                );

                $table->index(
                    ['production_line_id', 'status_code'],
                    'machine_status_line_code_index'
                );

                $table->index(
                    'source_updated_at',
                    'machine_status_source_updated_index'
                );
            }
        );

        Schema::create(
            'erp_maintenance_history',
            function (Blueprint $table) {
                $table->id();
                $table->uuid('external_id')->unique();

                $table->foreignId('machine_id')
                    ->constrained('erp_machines')
                    ->restrictOnDelete();

                $table->foreignId('production_line_id')
                    ->constrained('erp_production_lines')
                    ->restrictOnDelete();

                $table->foreignId('downtime_event_id')
                    ->nullable()
                    ->constrained('erp_downtime_events')
                    ->nullOnDelete();

                $table->string(
                    'maintenance_number',
                    180
                )->unique();

                $table->string(
                    'maintenance_type',
                    50
                );

                $table->string('priority', 30);
                $table->string('status', 40)
                    ->default('completed');

                $table->dateTime('reported_at');
                $table->dateTime('started_at');
                $table->dateTime('completed_at')->nullable();

                $table->unsignedSmallInteger(
                    'repair_duration_minutes'
                )->nullable();

                $table->string(
                    'failure_code',
                    100
                )->nullable();

                $table->text(
                    'failure_description'
                )->nullable();

                $table->text('root_cause')->nullable();
                $table->text('actions_taken')->nullable();

                $table->string(
                    'technician_name',
                    150
                )->nullable();

                $table->decimal(
                    'parts_cost',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'labor_cost',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'total_cost',
                    12,
                    2
                )->default(0);

                $table->char(
                    'currency_code',
                    3
                )->default('MAD');

                $table->boolean('is_late_arrival')
                    ->default(false);

                $table->dateTime('source_updated_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    ['machine_id', 'started_at'],
                    'maintenance_machine_start_index'
                );

                $table->index(
                    ['maintenance_type', 'status'],
                    'maintenance_type_status_index'
                );

                $table->index(
                    'source_updated_at',
                    'maintenance_source_updated_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_maintenance_history');
        Schema::dropIfExists('erp_machine_status_events');
        Schema::dropIfExists('erp_downtime_events');
    }
};