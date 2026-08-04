<?php

namespace Tests\Feature\ERP;

use App\Services\ERP\Sync\ErpMappedEntityPersister;
use ReflectionMethod;
use Tests\TestCase;

class ErpPersistenceIdempotencyTest extends TestCase
{
    private ErpMappedEntityPersister $persister;

    private ReflectionMethod $shouldSkip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persister = app(
            ErpMappedEntityPersister::class
        );

        $this->shouldSkip =
            new ReflectionMethod(
                ErpMappedEntityPersister::class,
                'shouldSkip'
            );

        $this->shouldSkip->setAccessible(
            true
        );
    }

    public function test_equivalent_business_data_is_skipped_when_sync_metadata_changes(): void
    {
        $existing = (object) [
            'id' => 1,
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 1,

            'source_checksum' =>
                'checksum-calculated-before-local-sync',

            'source_updated_at' =>
                '2026-07-01 00:00:00',

            'last_synced_at' =>
                '2026-07-31 10:00:00',

            'updated_at' =>
                '2026-07-31 10:00:00',

            'code' => 'VP-ORANGE-1L',
            'name' => 'Valencia Premium Orange 1 L',
            'product_family_id' => 1,

            /*
             * MySQL commonly returns DECIMAL as a string.
             */
            'nominal_volume' => '1.000',

            'is_active' => 1,
        ];

        $payload = [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 1,

            /*
             * This checksum may change because the simulator response
             * contains mutable local synchronization metadata.
             */
            'source_checksum' =>
                'checksum-calculated-after-local-sync',

            'source_updated_at' =>
                '2026-07-01 00:00:00',

            'last_synced_at' =>
                '2026-07-31 11:00:00',

            'updated_at' =>
                '2026-07-31 11:00:00',

            'code' => 'VP-ORANGE-1L',
            'name' => 'Valencia Premium Orange 1 L',
            'product_family_id' => 1,

            /*
             * The ERP mapper may return the same decimal as a float.
             */
            'nominal_volume' => 1.0,

            'is_active' => true,
        ];

        $result = $this->shouldSkip->invoke(
            $this->persister,
            $existing,
            $payload,
            array_keys($payload)
        );

        $this->assertTrue($result);
    }

    public function test_newer_source_version_is_not_skipped(): void
    {
        $existing = (object) [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 1,

            'source_checksum' =>
                'same-checksum',

            'source_updated_at' =>
                '2026-07-01 00:00:00',

            'name' => 'Valencia Premium Orange 1 L',
            'nominal_volume' => '1.000',
        ];

        $payload = [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 2,

            'source_checksum' =>
                'same-checksum',

            'source_updated_at' =>
                '2026-07-02 00:00:00',

            'name' => 'Valencia Premium Orange 1 L',
            'nominal_volume' => 1.0,
        ];

        $result = $this->shouldSkip->invoke(
            $this->persister,
            $existing,
            $payload,
            array_keys($payload)
        );

        $this->assertFalse($result);
    }

    public function test_real_business_change_is_not_skipped_at_same_version(): void
    {
        $existing = (object) [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 1,

            'source_checksum' =>
                'old-checksum',

            'source_updated_at' =>
                '2026-07-01 00:00:00',

            'name' => 'Valencia Premium Orange 1 L',
            'nominal_volume' => '1.000',
            'is_active' => 1,
        ];

        $payload = [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 1,

            'source_checksum' =>
                'new-checksum',

            'source_updated_at' =>
                '2026-07-01 00:00:00',

            /*
             * Real business-data modification.
             */
            'name' => 'Valencia Premium Orange Updated',
            'nominal_volume' => 1.0,
            'is_active' => true,
        ];

        $result = $this->shouldSkip->invoke(
            $this->persister,
            $existing,
            $payload,
            array_keys($payload)
        );

        $this->assertFalse($result);
    }

    public function test_older_source_version_is_skipped(): void
    {
        $existing = (object) [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 4,

            'source_checksum' =>
                'newer-local-checksum',

            'source_updated_at' =>
                '2026-07-04 00:00:00',

            'name' => 'Current product',
        ];

        $payload = [
            'source_system' => 'simulated_sage',
            'external_id' => 'PRD-001',
            'source_version' => 3,

            'source_checksum' =>
                'older-source-checksum',

            'source_updated_at' =>
                '2026-07-03 00:00:00',

            'name' => 'Stale product',
        ];

        $result = $this->shouldSkip->invoke(
            $this->persister,
            $existing,
            $payload,
            array_keys($payload)
        );

        $this->assertTrue($result);
    }
}