<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'machine_status_events',
            function (Blueprint $table): void {
                $table
                    ->char(
                        'source_checksum',
                        64
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'last_synced_at'
                    )
                    ->nullable();

                $table
                    ->string(
                        'import_status',
                        30
                    )
                    ->default('imported');

                $table
                    ->text(
                        'import_error'
                    )
                    ->nullable();

                $table->index(
                    [
                        'import_status',
                        'last_synced_at',
                    ],
                    'machine_status_import_sync_idx'
                );
            }
        );

        Schema::table(
            'maintenance_history',
            function (Blueprint $table): void {
                $table
                    ->char(
                        'source_checksum',
                        64
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'last_synced_at'
                    )
                    ->nullable();

                $table
                    ->string(
                        'import_status',
                        30
                    )
                    ->default('imported');

                $table
                    ->text(
                        'import_error'
                    )
                    ->nullable();

                $table->index(
                    [
                        'import_status',
                        'last_synced_at',
                    ],
                    'maintenance_import_sync_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'maintenance_history',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'maintenance_import_sync_idx'
                );

                $table->dropColumn([
                    'source_checksum',
                    'last_synced_at',
                    'import_status',
                    'import_error',
                ]);
            }
        );

        Schema::table(
            'machine_status_events',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'machine_status_import_sync_idx'
                );

                $table->dropColumn([
                    'source_checksum',
                    'last_synced_at',
                    'import_status',
                    'import_error',
                ]);
            }
        );
    }
};