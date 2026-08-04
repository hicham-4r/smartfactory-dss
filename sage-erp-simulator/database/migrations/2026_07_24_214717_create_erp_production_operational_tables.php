<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_production_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('product_id')
                ->constrained('erp_products')
                ->restrictOnDelete();

            $table->foreignId('production_line_id')
                ->constrained('erp_production_lines')
                ->restrictOnDelete();

            $table->string('order_number', 120)->unique();

            $table->dateTime('planned_start_at');
            $table->dateTime('planned_end_at');

            $table->unsignedInteger('planned_quantity');

            $table->unsignedTinyInteger('priority')->default(3);

            $table->string('status', 50)->default('planned');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['production_line_id', 'planned_start_at'],
                'production_order_line_start_index'
            );

            $table->index(
                ['product_id', 'status'],
                'production_order_product_status_index'
            );
        });

        Schema::create('erp_production_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('production_order_id')
                ->constrained('erp_production_orders')
                ->cascadeOnDelete();

            $table->foreignId('shift_id')
                ->constrained('erp_shifts')
                ->restrictOnDelete();

            $table->foreignId('operator_assignment_id')
                ->nullable()
                ->constrained('erp_operator_assignments')
                ->nullOnDelete();

            $table->string('batch_number', 150)->unique();
            $table->string('lot_number', 150)->unique();

            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');

            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();

            $table->unsignedInteger('planned_quantity');

            $table->unsignedInteger('gross_quantity')->default(0);
            $table->unsignedInteger('good_quantity')->default(0);
            $table->unsignedInteger('rejected_quantity')->default(0);

            $table->string('status', 50)->default('scheduled');

            $table->string('quality_status', 50)
                ->default('pending');

            $table->date('expiry_date')->nullable();

            $table->timestamps();

            $table->index(
                ['production_order_id', 'status'],
                'production_batch_order_status_index'
            );

            $table->index(
                ['shift_id', 'scheduled_start_at'],
                'production_batch_shift_start_index'
            );

            $table->index('quality_status');
        });

        Schema::create('erp_production_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('production_batch_id')
                ->constrained('erp_production_batches')
                ->cascadeOnDelete();

            $table->foreignId('machine_id')
                ->nullable()
                ->constrained('erp_machines')
                ->nullOnDelete();

            $table->foreignId('process_stage_id')
                ->nullable()
                ->constrained('erp_process_stages')
                ->nullOnDelete();

            $table->string('record_number', 180)->unique();

            $table->dateTime('interval_start_at');
            $table->dateTime('interval_end_at');
            $table->dateTime('recorded_at');

            $table->unsignedInteger('target_quantity');
            $table->unsignedInteger('gross_quantity');
            $table->unsignedInteger('good_quantity');
            $table->unsignedInteger('rejected_quantity');

            $table->unsignedSmallInteger('runtime_minutes');
            $table->unsignedSmallInteger('downtime_minutes');

            $table->decimal(
                'quality_rate_percent',
                5,
                2
            )->nullable();

            $table->boolean('is_late_arrival')->default(false);

            $table->dateTime('source_updated_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['production_batch_id', 'interval_start_at'],
                'production_record_batch_interval_unique'
            );

            $table->index(
                ['recorded_at', 'production_batch_id'],
                'production_record_recorded_batch_index'
            );

            $table->index(
                'source_updated_at',
                'production_record_source_updated_index'
            );

            $table->index(
                ['machine_id', 'recorded_at'],
                'production_record_machine_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_production_records');
        Schema::dropIfExists('erp_production_batches');
        Schema::dropIfExists('erp_production_orders');
    }
};