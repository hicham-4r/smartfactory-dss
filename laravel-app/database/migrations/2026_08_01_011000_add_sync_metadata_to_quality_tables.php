<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addSyncMetadata(
            'finished_lots',
            'finished_lots_import_sync_idx'
        );

        $this->addSyncMetadata(
            'inspections',
            'inspections_import_sync_idx'
        );

        $this->addSyncMetadata(
            'nonconformities',
            'nonconformities_import_sync_idx'
        );
    }

    public function down(): void
    {
        $this->dropSyncMetadata(
            'nonconformities',
            'nonconformities_import_sync_idx'
        );

        $this->dropSyncMetadata(
            'inspections',
            'inspections_import_sync_idx'
        );

        $this->dropSyncMetadata(
            'finished_lots',
            'finished_lots_import_sync_idx'
        );
    }

    private function addSyncMetadata(
        string $tableName,
        string $indexName
    ): void {
        Schema::table(
            $tableName,
            function (
                Blueprint $table
            ) use (
                $indexName
            ): void {
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
                    $indexName
                );
            }
        );
    }

    private function dropSyncMetadata(
        string $tableName,
        string $indexName
    ): void {
        Schema::table(
            $tableName,
            function (
                Blueprint $table
            ) use (
                $indexName
            ): void {
                $table->dropIndex(
                    $indexName
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
