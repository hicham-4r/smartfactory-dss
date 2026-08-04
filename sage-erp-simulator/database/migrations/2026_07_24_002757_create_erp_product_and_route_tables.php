<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('product_family_id')
                ->constrained('erp_product_families')
                ->restrictOnDelete();

            $table->foreignId('packaging_format_id')
                ->constrained('erp_packaging_formats')
                ->restrictOnDelete();

            $table->string('code', 80)->unique();
            $table->string('name', 180);
            $table->string('flavor', 100);
            $table->string('beverage_type', 80);

            $table->boolean('contains_milk')->default(false);
            $table->unsignedSmallInteger('shelf_life_days')->nullable();

            $table->string('status', 50)->default('active');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('flavor');
            $table->index('beverage_type');
            $table->index('status');
        });

        Schema::create('erp_product_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('erp_products')
                ->cascadeOnDelete();

            $table->foreignId('production_line_id')
                ->constrained('erp_production_lines')
                ->cascadeOnDelete();

            $table->boolean('is_preferred')->default(false);

            $table->unsignedInteger(
                'nominal_rate_units_per_hour'
            )->nullable();

            $table->timestamps();

            $table->unique(
                ['product_id', 'production_line_id'],
                'product_line_unique'
            );
        });

        Schema::create('erp_product_routes', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignId('product_id')
                ->constrained('erp_products')
                ->cascadeOnDelete();

            $table->string('code', 100)->unique();
            $table->string('name', 180);

            $table->unsignedSmallInteger('version')->default(1);
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['product_id', 'version'],
                'product_route_version_unique'
            );
        });

        Schema::create('erp_product_route_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_route_id')
                ->constrained('erp_product_routes')
                ->cascadeOnDelete();

            $table->foreignId('process_stage_id')
                ->constrained('erp_process_stages')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('sequence_order');

            $table->boolean('is_required')->default(true);

            $table->unsignedSmallInteger(
                'target_duration_minutes'
            )->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['product_route_id', 'sequence_order'],
                'route_step_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_product_route_steps');
        Schema::dropIfExists('erp_product_routes');
        Schema::dropIfExists('erp_product_lines');
        Schema::dropIfExists('erp_products');
    }
};