<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'erp_quality_inspections',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('external_id')->unique();

                $table->foreignId('production_batch_id')
                    ->constrained('erp_production_batches')
                    ->cascadeOnDelete();

                $table->foreignId('product_id')
                    ->constrained('erp_products')
                    ->restrictOnDelete();

                $table->foreignId('production_line_id')
                    ->constrained('erp_production_lines')
                    ->restrictOnDelete();

                $table->foreignId('shift_id')
                    ->constrained('erp_shifts')
                    ->restrictOnDelete();

                $table->string(
                    'inspection_number',
                    180
                )->unique();

                $table->string(
                    'inspection_type',
                    80
                )->default('final_release');

                $table->dateTime('sampled_at');
                $table->dateTime('inspection_started_at');
                $table->dateTime('inspection_completed_at')
                    ->nullable();

                $table->string(
                    'inspector_name',
                    150
                )->nullable();

                $table->string('status', 40)
                    ->default('completed');

                $table->string('result', 40);

                $table->decimal(
                    'overall_score_percent',
                    5,
                    2
                )->nullable();

                $table->string(
                    'nonconformity_code',
                    100
                )->nullable();

                $table->text(
                    'nonconformity_description'
                )->nullable();

                $table->text(
                    'corrective_action'
                )->nullable();

                $table->boolean('is_late_arrival')
                    ->default(false);

                $table->dateTime('source_updated_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    'production_batch_id',
                    'quality_inspection_batch_unique'
                );

                $table->index(
                    ['production_line_id', 'sampled_at'],
                    'quality_line_sampled_index'
                );

                $table->index(
                    ['product_id', 'result'],
                    'quality_product_result_index'
                );

                $table->index(
                    'source_updated_at',
                    'quality_source_updated_index'
                );
            }
        );

        Schema::create(
            'erp_quality_test_results',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('quality_inspection_id')
                    ->constrained('erp_quality_inspections')
                    ->cascadeOnDelete();

                $table->string('test_code', 100);
                $table->string('test_name', 180);
                $table->string('test_category', 80);

                $table->decimal(
                    'numeric_value',
                    12,
                    4
                )->nullable();

                $table->string(
                    'text_value',
                    180
                )->nullable();

                $table->string('unit', 30)->nullable();

                $table->decimal(
                    'minimum_specification',
                    12,
                    4
                )->nullable();

                $table->decimal(
                    'maximum_specification',
                    12,
                    4
                )->nullable();

                $table->string('result', 40);
                $table->dateTime('tested_at');

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'quality_inspection_id',
                        'test_code',
                    ],
                    'inspection_test_code_unique'
                );

                $table->index(
                    ['test_code', 'result'],
                    'quality_test_code_result_index'
                );
            }
        );

        Schema::create(
            'erp_finished_lot_releases',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('external_id')->unique();

                $table->foreignId('production_batch_id')
                    ->constrained('erp_production_batches')
                    ->cascadeOnDelete();

                $table->foreignId('quality_inspection_id')
                    ->constrained('erp_quality_inspections')
                    ->cascadeOnDelete();

                $table->string(
                    'release_number',
                    180
                )->unique();

                $table->string('lot_number', 150);

                $table->string('decision', 40);

                $table->string(
                    'warehouse_status',
                    50
                );

                $table->dateTime('decision_at');
                $table->dateTime('released_at')->nullable();

                $table->string(
                    'released_by',
                    150
                )->nullable();

                $table->string(
                    'quality_certificate_number',
                    180
                )->nullable();

                $table->unsignedInteger(
                    'approved_quantity'
                )->default(0);

                $table->unsignedInteger(
                    'blocked_quantity'
                )->default(0);

                $table->unsignedInteger(
                    'rejected_quantity'
                )->default(0);

                $table->date('expiry_date')->nullable();

                $table->text('decision_reason')->nullable();

                $table->boolean('is_late_arrival')
                    ->default(false);

                $table->dateTime('source_updated_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    'production_batch_id',
                    'finished_release_batch_unique'
                );

                $table->unique(
                    'quality_inspection_id',
                    'finished_release_inspection_unique'
                );

                $table->index(
                    ['decision', 'decision_at'],
                    'finished_release_decision_index'
                );

                $table->index(
                    ['warehouse_status', 'expiry_date'],
                    'finished_release_warehouse_expiry_index'
                );

                $table->index(
                    'source_updated_at',
                    'finished_release_source_updated_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'erp_finished_lot_releases'
        );

        Schema::dropIfExists(
            'erp_quality_test_results'
        );

        Schema::dropIfExists(
            'erp_quality_inspections'
        );
    }
};