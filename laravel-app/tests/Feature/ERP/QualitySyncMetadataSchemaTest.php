<?php

namespace Tests\Feature\ERP;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QualitySyncMetadataSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function metadataColumns(): array
    {
        return [
            'source_checksum',
            'last_synced_at',
            'import_status',
            'import_error',
        ];
    }

    public function test_finished_lots_have_sync_metadata(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'finished_lots',
                $this->metadataColumns()
            )
        );
    }

    public function test_inspections_have_sync_metadata(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'inspections',
                $this->metadataColumns()
            )
        );
    }

    public function test_nonconformities_have_sync_metadata(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'nonconformities',
                $this->metadataColumns()
            )
        );
    }

    public function test_finished_lot_import_status_defaults_to_imported(): void
    {
        DB::table(
            'finished_lots'
        )->insert([
            'external_id' =>
                'TEST-FINISHED-LOT-001',

            'lot_number' =>
                'LOT-TEST-001',

            'batch_external_id' =>
                'BATCH-TEST-001',

            'product_external_id' =>
                'PRODUCT-TEST-001',

            'status' =>
                'released',

            'produced_at' =>
                '2026-07-01 10:00:00',

            'produced_quantity' =>
                100,

            'released_quantity' =>
                100,

            'rejected_quantity' =>
                0,

            'quantity_unit' =>
                'bottles',

            'source_version' =>
                1,

            'source_updated_at' =>
                '2026-07-01 10:00:00',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->assertDatabaseHas(
            'finished_lots',
            [
                'external_id' =>
                    'TEST-FINISHED-LOT-001',

                'import_status' =>
                    'imported',
            ]
        );
    }

    public function test_inspection_import_status_defaults_to_imported(): void
    {
        DB::table(
            'inspections'
        )->insert([
            'external_id' =>
                'TEST-INSPECTION-001',

            'inspection_number' =>
                'INSPECTION-TEST-001',

            'batch_external_id' =>
                'BATCH-TEST-001',

            'inspection_type' =>
                'final_release',

            'result' =>
                'passed',

            'inspected_at' =>
                '2026-07-01 11:00:00',

            'source_version' =>
                1,

            'source_updated_at' =>
                '2026-07-01 11:00:00',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->assertDatabaseHas(
            'inspections',
            [
                'external_id' =>
                    'TEST-INSPECTION-001',

                'import_status' =>
                    'imported',
            ]
        );
    }

    public function test_nonconformity_import_status_defaults_to_imported(): void
    {
        DB::table(
            'nonconformities'
        )->insert([
            'external_id' =>
                'TEST-NONCONFORMITY-001',

            'nonconformity_number' =>
                'NC-TEST-001',

            'inspection_external_id' =>
                'TEST-INSPECTION-001',

            'batch_external_id' =>
                'BATCH-TEST-001',

            'severity' =>
                'major',

            'status' =>
                'open',

            'category' =>
                'quality',

            'description' =>
                'Synthetic test nonconformity.',

            'detected_at' =>
                '2026-07-01 11:00:00',

            'source_version' =>
                1,

            'source_updated_at' =>
                '2026-07-01 11:00:00',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->assertDatabaseHas(
            'nonconformities',
            [
                'external_id' =>
                    'TEST-NONCONFORMITY-001',

                'import_status' =>
                    'imported',
            ]
        );
    }
}
