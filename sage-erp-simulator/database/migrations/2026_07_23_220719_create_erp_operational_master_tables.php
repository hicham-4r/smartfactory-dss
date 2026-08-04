<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_machines', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->string('code', 80)->unique();
            $table->string('name', 180);
            $table->string('machine_type', 100);

            $table->string('manufacturer', 150)->nullable();
            $table->string('model_reference', 150)->nullable();
            $table->string('serial_number', 150)->nullable()->unique();

            $table->string('status', 50)->default('operational');
            $table->string('criticality', 30)->default('medium');

            $table->date('installation_date')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('machine_type');
            $table->index('status');
            $table->index('criticality');
        });

        Schema::create('erp_line_machines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('production_line_id')
                ->constrained('erp_production_lines')
                ->cascadeOnDelete();

            $table->foreignId('machine_id')
                ->constrained('erp_machines')
                ->cascadeOnDelete();

            $table->foreignId('process_stage_id')
                ->nullable()
                ->constrained('erp_process_stages')
                ->nullOnDelete();

            $table->unsignedTinyInteger('sequence_order');
            $table->string('station_code', 50);

            $table->boolean('is_primary')->default(true);
            $table->date('assigned_at')->nullable();

            $table->timestamps();

            // A physical machine belongs to only one line
            // in the current simulator configuration.
            $table->unique('machine_id');

            $table->unique(
                ['production_line_id', 'sequence_order'],
                'line_machine_sequence_unique'
            );

            $table->unique(
                ['production_line_id', 'station_code'],
                'line_station_code_unique'
            );
        });

        Schema::create('erp_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->string('code', 50)->unique();
            $table->string('name', 100);

            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('crosses_midnight')->default(false);
            $table->string('status', 50)->default('active');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('erp_operators', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->string('employee_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            $table->string('email', 180)->nullable()->unique();
            $table->string('phone', 50)->nullable();

            $table->date('hire_date')->nullable();
            $table->string('status', 50)->default('active');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('status');
        });

        Schema::create('erp_operator_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('operator_id')
                ->constrained('erp_operators')
                ->cascadeOnDelete();

            $table->foreignId('production_line_id')
                ->constrained('erp_production_lines')
                ->cascadeOnDelete();

            $table->foreignId('shift_id')
                ->constrained('erp_shifts')
                ->cascadeOnDelete();

            $table->string('role_on_line', 80)
                ->default('line_operator');

            $table->date('assigned_from');
            $table->date('assigned_until')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['operator_id', 'assigned_from'],
                'operator_assignment_start_unique'
            );

            $table->index(
                ['production_line_id', 'shift_id', 'is_active'],
                'line_shift_active_assignment_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_operator_assignments');
        Schema::dropIfExists('erp_operators');
        Schema::dropIfExists('erp_shifts');
        Schema::dropIfExists('erp_line_machines');
        Schema::dropIfExists('erp_machines');
    }
};