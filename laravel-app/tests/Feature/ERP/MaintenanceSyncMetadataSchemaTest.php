<?php

namespace Tests\Feature\ERP;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceSyncMetadataSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_status_events_have_sync_metadata(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'machine_status_events',
                [
                    'source_checksum',
                    'last_synced_at',
                    'import_status',
                    'import_error',
                ]
            )
        );
    }

    public function test_maintenance_history_has_sync_metadata(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'maintenance_history',
                [
                    'source_checksum',
                    'last_synced_at',
                    'import_status',
                    'import_error',
                ]
            )
        );
    }

    public function test_machine_status_import_status_defaults_to_imported(): void
    {
        DB::table(
            'machine_status_events'
        )->insert([
            'external_id' =>
                'TEST-MACHINE-STATUS-001',

            'machine_external_id' =>
                'TEST-MACHINE-001',

            'status' =>
                'running',

            'occurred_at' =>
                '2026-07-01 08:00:00',

            'source_version' =>
                1,

            'source_updated_at' =>
                '2026-07-01 08:00:00',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->assertDatabaseHas(
            'machine_status_events',
            [
                'external_id' =>
                    'TEST-MACHINE-STATUS-001',

                'import_status' =>
                    'imported',
            ]
        );
    }

    public function test_maintenance_import_status_defaults_to_imported(): void
    {
        DB::table(
            'maintenance_history'
        )->insert([
            'external_id' =>
                'TEST-MAINTENANCE-001',

            'maintenance_number' =>
                'MAINT-TEST-001',

            'machine_external_id' =>
                'TEST-MACHINE-001',

            'maintenance_type' =>
                'preventive',

            'status' =>
                'completed',

            'downtime_minutes' =>
                30,

            'source_version' =>
                1,

            'source_updated_at' =>
                '2026-07-01 08:00:00',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->assertDatabaseHas(
            'maintenance_history',
            [
                'external_id' =>
                    'TEST-MAINTENANCE-001',

                'import_status' =>
                    'imported',
            ]
        );
    }
}