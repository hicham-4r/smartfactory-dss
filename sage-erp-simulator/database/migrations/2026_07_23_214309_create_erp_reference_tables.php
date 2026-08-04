<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_product_families', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('erp_packaging_formats', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->unsignedInteger('volume_ml');
            $table->string('package_type', 50)->default('carton');
            $table->string('closure_type', 50)->default('cap');
            $table->boolean('has_straw')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('erp_production_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active');
            $table->unsignedInteger(
                'nominal_capacity_units_per_hour'
            )->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('erp_process_stages', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('code', 80)->unique();
            $table->string('name', 180);
            $table->unsignedTinyInteger('sequence_order');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_process_stages');
        Schema::dropIfExists('erp_production_lines');
        Schema::dropIfExists('erp_packaging_formats');
        Schema::dropIfExists('erp_product_families');
    }
};