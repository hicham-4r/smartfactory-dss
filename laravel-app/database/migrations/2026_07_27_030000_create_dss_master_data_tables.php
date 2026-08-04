<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the DSS-side production master-data structure.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Product families
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'product_families',
            function (Blueprint $table): void {
                $table->id();

                $table->string('code', 50)
                    ->unique();

                $table->string('name', 150);

                $table->text('description')
                    ->nullable();

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'product_families'
                );

                $table->timestamps();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'products',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'product_family_id'
                )
                    ->constrained('product_families')
                    ->restrictOnDelete();

                $table->string('code', 50)
                    ->unique();

                $table->string('sku', 100)
                    ->nullable()
                    ->unique();

                $table->string('name', 180);

                $table->string('base_unit', 30)
                    ->nullable();

                $table->string('package_format', 80)
                    ->nullable();

                $table->decimal(
                    'nominal_volume',
                    12,
                    3
                )->nullable();

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'products'
                );

                $table->timestamps();

                $table->index(
                    [
                        'product_family_id',
                        'is_active',
                    ],
                    'products_family_active_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Production lines
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'production_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->string('code', 50)
                    ->unique();

                $table->string('name', 150);

                $table->string('plant_area', 100)
                    ->nullable();

                $table->text('description')
                    ->nullable();

                $table->decimal(
                    'nominal_capacity_per_hour',
                    14,
                    3
                )->nullable();

                $table->string('capacity_unit', 30)
                    ->nullable();

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'production_lines'
                );

                $table->timestamps();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Machines
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'machines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'production_line_id'
                )
                    ->constrained('production_lines')
                    ->restrictOnDelete();

                $table->string('code', 50)
                    ->unique();

                $table->string('name', 150);

                $table->string('machine_type', 100);

                $table->string('manufacturer', 120)
                    ->nullable();

                $table->string('model', 120)
                    ->nullable();

                $table->string('serial_number', 120)
                    ->nullable()
                    ->unique();

                $table->unsignedSmallInteger(
                    'sequence_number'
                )->nullable();

                $table->boolean('is_critical')
                    ->default(false);

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'machines'
                );

                $table->timestamps();

                $table->unique(
                    [
                        'production_line_id',
                        'sequence_number',
                    ],
                    'machines_line_sequence_unique'
                );

                $table->index(
                    [
                        'production_line_id',
                        'machine_type',
                    ],
                    'machines_line_type_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Shifts
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'shifts',
            function (Blueprint $table): void {
                $table->id();

                $table->string('code', 50)
                    ->unique();

                $table->string('name', 100);

                $table->time('starts_at');

                $table->time('ends_at');

                $table->boolean('crosses_midnight')
                    ->default(false);

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'shifts'
                );

                $table->timestamps();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Operators
        |--------------------------------------------------------------------------
        |
        | An ERP operator may optionally be connected to a DSS login account.
        | The employee record and the authentication account remain separate.
        |
        */

        Schema::create(
            'operators',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->nullable()
                    ->unique()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('employee_code', 80)
                    ->unique();

                $table->string('first_name', 100);

                $table->string('last_name', 100);

                $table->string('email', 255)
                    ->nullable();

                $table->string('phone', 40)
                    ->nullable();

                $table->date('hired_on')
                    ->nullable();

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'operators'
                );

                $table->timestamps();

                $table->index(
                    [
                        'last_name',
                        'first_name',
                    ],
                    'operators_name_index'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Operator assignments
        |--------------------------------------------------------------------------
        */

        Schema::create(
            'operator_assignments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('operator_id')
                    ->constrained('operators')
                    ->restrictOnDelete();

                $table->foreignId(
                    'production_line_id'
                )
                    ->constrained('production_lines')
                    ->restrictOnDelete();

                $table->foreignId('shift_id')
                    ->constrained('shifts')
                    ->restrictOnDelete();

                $table->foreignId('assigned_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->date('starts_on');

                $table->date('ends_on')
                    ->nullable();

                $table->boolean('is_primary')
                    ->default(false);

                $table->boolean('is_active')
                    ->default(true)
                    ->index();

                $this->addSourceMetadata(
                    $table,
                    'operator_assignments'
                );

                $table->timestamps();

                $table->unique(
                    [
                        'operator_id',
                        'production_line_id',
                        'shift_id',
                        'starts_on',
                    ],
                    'operator_assignment_period_unique'
                );

                $table->index(
                    [
                        'production_line_id',
                        'shift_id',
                        'is_active',
                    ],
                    'operator_assignment_work_index'
                );

                $table->index(
                    [
                        'operator_id',
                        'is_active',
                    ],
                    'operator_assignment_operator_index'
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
            'operator_assignments'
        );

        Schema::dropIfExists('operators');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('machines');

        Schema::dropIfExists(
            'production_lines'
        );

        Schema::dropIfExists('products');

        Schema::dropIfExists(
            'product_families'
        );
    }

    /**
     * Add ERP/source traceability fields.
     */
    private function addSourceMetadata(
        Blueprint $table,
        string $indexPrefix
    ): void {
        $table->string('source_system', 50)
            ->default('manual');

        $table->string('external_id', 120)
            ->nullable();

        $table->unsignedBigInteger('source_version')
            ->nullable();

        $table->char('source_checksum', 64)
            ->nullable();

        $table->timestamp('source_updated_at')
            ->nullable();

        $table->timestamp('last_synced_at')
            ->nullable();

        $table->unique(
            [
                'source_system',
                'external_id',
            ],
            $indexPrefix.'_source_external_unique'
        );

        $table->index(
            [
                'source_system',
                'last_synced_at',
            ],
            $indexPrefix.'_source_sync_index'
        );
    }
};