<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\BatchErpData;
use App\DTOs\ERP\Mapped\DowntimeEventErpData;
use App\DTOs\ERP\Mapped\FinishedLotErpData;
use App\DTOs\ERP\Mapped\InspectionErpData;
use App\DTOs\ERP\Mapped\ProductionRecordErpData;
use App\DTOs\ERP\Mapped\MachineStatusEventErpData;
use App\DTOs\ERP\Mapped\MaintenanceHistoryErpData;
use App\DTOs\ERP\Mapped\NonconformityErpData;
use App\DTOs\ERP\Mapped\RunLogErpData;
use App\DTOs\ERP\Mapped\WorkOrderErpData;
use App\Enums\ERP\ErpFinishedLotStatus;
use App\Enums\ERP\ErpResource;
use App\Enums\Production\ProductionOrderStatus;
use App\Exceptions\ERP\ErpMappingException;
use App\Services\ERP\Mapping\SimulatedSageRecordMapper;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErpOperationalDataMappingTest extends TestCase
{
    public function test_service_container_resolves_complete_simulated_sage_mapper(): void
    {
        $mapper = app(
            ErpRecordMapperInterface::class
        );

        $this->assertInstanceOf(
            SimulatedSageRecordMapper::class,
            $mapper
        );

        $this->assertSame(
            'Simulated Sage ERP mapper',
            $mapper->name()
        );

        foreach (ErpResource::cases() as $resource) {
            $record = $this->record(
                resource: $resource,
                externalId:
                    'SUPPORT-'.$resource->value,
                attributes: []
            );

            $this->assertTrue(
                $mapper->supports($record)
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param class-string<ErpMappedEntityInterface> $expectedClass
     */
    #[DataProvider('operationalDataProvider')]
    public function test_operational_resources_are_mapped_to_typed_dtos(
        ErpResource $resource,
        array $payload,
        string $expectedClass
    ): void {
        $source = $this->record(
            resource: $resource,
            externalId:
                'SOURCE-'.$resource->value,
            attributes: $payload
        );

        $mapped = app(
            ErpRecordMapperInterface::class
        )->map($source);

        $this->assertInstanceOf(
            $expectedClass,
            $mapped
        );

        $this->assertSame(
            $resource,
            $mapped->resource()
        );

        $this->assertSame(
            $source,
            $mapped->source()
        );

        $serialized = $mapped->toArray();

        $this->assertArrayHasKey(
            'source',
            $serialized
        );

        $this->assertArrayHasKey(
            'data',
            $serialized
        );

        $this->assertSame(
            $source->checksum,
            $serialized['source']['checksum']
        );
    }

    public function test_documented_status_aliases_are_normalized(): void
    {
        $record = $this->record(
            resource:
                ErpResource::WorkOrders,

            externalId:
                'WORK-ORDER-ALIAS',

            attributes: [
                'work_order_number' =>
                    'WO-ALIAS-001',

                'product_id' =>
                    'PRODUCT-001',

                'line_id' =>
                    'LINE-001',

                'shift_id' =>
                    'SHIFT-A',

                'state' =>
                    'running',

                'planned_start' =>
                    '2026-07-30 06:00:00',

                'planned_end' =>
                    '2026-07-30 14:00:00',

                'planned_quantity' =>
                    1500,

                'uom' =>
                    'bottles',

                'priority_level' =>
                    2,
            ]
        );

        $mapped = app(
            ErpRecordMapperInterface::class
        )->map($record);

        $this->assertInstanceOf(
            WorkOrderErpData::class,
            $mapped
        );

        $this->assertSame(
            ProductionOrderStatus::InProgress,
            $mapped->status
        );

        $this->assertSame(
            '1500.000',
            $mapped->targetQuantity
        );

        $this->assertSame(
            2,
            $mapped->priority
        );
    }

    public function test_machine_run_inconsistent_quantities_are_rejected(): void
    {
        $record = $this->record(
            resource:
                ErpResource::MachineRuns,

            externalId:
                'RUN-INVALID-QUANTITY',

            attributes: [
                'run_number' => 'RUN-001',
                'batch_id' => 'BATCH-001',
                'machine_id' => 'MACHINE-001',
                'status' => 'running',

                'started_at' =>
                    '2026-07-30 06:00:00',

                'produced_quantity' =>
                    '1000.000',

                'good_quantity' =>
                    '900.000',

                'rejected_quantity' =>
                    '50.000',

                'quantity_unit' =>
                    'bottles',

                'runtime_minutes' => 120,
                'downtime_minutes' => 0,
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_inspection_counts_cannot_exceed_sample_size(): void
    {
        $record = $this->record(
            resource:
                ErpResource::Inspections,

            externalId:
                'INSPECTION-INVALID-SAMPLE',

            attributes: [
                'inspection_number' =>
                    'INS-001',

                'batch_id' =>
                    'BATCH-001',

                'inspection_type' =>
                    'Packaging control',

                'result' =>
                    'failed',

                'inspected_at' =>
                    '2026-07-30 12:00:00',

                'sample_size' => 100,
                'passed_quantity' => 80,
                'failed_quantity' => 30,
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_released_finished_lot_requires_release_timestamp(): void
    {
        $record = $this->record(
            resource:
                ErpResource::FinishedLots,

            externalId:
                'LOT-MISSING-RELEASE-DATE',

            attributes: [
                'lot_number' =>
                    'LOT-001',

                'batch_id' =>
                    'BATCH-001',

                'product_id' =>
                    'PRODUCT-001',

                'status' =>
                    'released',

                'produced_at' =>
                    '2026-07-30 14:00:00',

                'produced_quantity' =>
                    '1000.000',

                'released_quantity' =>
                    '975.000',

                'rejected_quantity' =>
                    '25.000',

                'quantity_unit' =>
                    'bottles',
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_maintenance_completion_cannot_precede_start(): void
    {
        $record = $this->record(
            resource:
                ErpResource::MaintenanceHistory,

            externalId:
                'MAINTENANCE-INVALID-DATES',

            attributes: [
                'maintenance_number' =>
                    'MAINT-001',

                'machine_id' =>
                    'MACHINE-001',

                'maintenance_type' =>
                    'corrective',

                'status' =>
                    'completed',

                'started_at' =>
                    '2026-07-30 12:00:00',

                'completed_at' =>
                    '2026-07-30 11:00:00',
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_missing_relationship_is_rejected_without_exposing_payload(): void
    {
        $record = $this->record(
            resource:
                ErpResource::Nonconformities,

            externalId:
                'NC-MISSING-INSPECTION',

            attributes: [
                'nonconformity_number' =>
                    'NC-001',

                'severity' =>
                    'major',

                'status' =>
                    'open',

                'category' =>
                    'Packaging',

                'description' =>
                    'Synthetic defect.',

                'detected_at' =>
                    '2026-07-30 12:00:00',

                'private_payload_value' =>
                    'must-not-appear',
            ]
        );

        try {
            app(
                ErpRecordMapperInterface::class
            )->map($record);

            $this->fail(
                'The invalid nonconformity was accepted.'
            );
        } catch (
            ErpMappingException $exception
        ) {
            $this->assertStringContainsString(
                'inspection_external_id',
                $exception->getMessage()
            );

            $this->assertStringNotContainsString(
                'must-not-appear',
                $exception->getMessage()
            );

            $this->assertArrayNotHasKey(
                'private_payload_value',
                $exception->context()
            );

            $this->assertSame(
                'NC-MISSING-INSPECTION',
                $exception
                    ->context()['external_id']
            );
        }
    }

    /**
     * @return array<string, array{
     *     0: ErpResource,
     *     1: array<string, mixed>,
     *     2: class-string<ErpMappedEntityInterface>
     * }>
     */
    public static function operationalDataProvider(): array
    {
        return [
            'work order' => [
                ErpResource::WorkOrders,

                [
                    'order_number' =>
                        'WO-001',

                    'product_external_id' =>
                        'PRODUCT-001',

                    'production_line_external_id' =>
                        'LINE-001',

                    'shift_external_id' =>
                        'SHIFT-A',

                    'status' =>
                        'released',

                    'planned_start_at' =>
                        '2026-07-30 06:00:00',

                    'planned_end_at' =>
                        '2026-07-30 14:00:00',

                    'target_quantity' =>
                        '1000.000',

                    'quantity_unit' =>
                        'bottles',

                    'priority' => 2,

                    'instructions' =>
                        'Synthetic work order.',
                ],

                WorkOrderErpData::class,
            ],

            'batch' => [
                ErpResource::Batches,

                [
                    'batch_number' =>
                        'BATCH-001',

                    'work_order_external_id' =>
                        'WORK-ORDER-001',

                    'sequence_number' => 1,

                    'status' =>
                        'running',

                    'planned_quantity' =>
                        '1000.000',

                    'actual_good_quantity' =>
                        '0.000',

                    'actual_rejected_quantity' =>
                        '0.000',

                    'quantity_unit' =>
                        'bottles',

                    'scheduled_start_at' =>
                        '2026-07-30 06:00:00',

                    'actual_start_at' =>
                        '2026-07-30 06:05:00',
                ],

                BatchErpData::class,
            ],

            'machine run' => [
                ErpResource::MachineRuns,

                [
                    'run_number' =>
                        'RUN-001',

                    'batch_external_id' =>
                        'BATCH-001',

                    'production_line_external_id' =>
                        'LINE-001',

                    'machine_external_id' =>
                        'MACHINE-001',

                    'shift_external_id' =>
                        'SHIFT-A',

                    'operator_external_id' =>
                        'OPERATOR-001',

                    'status' =>
                        'completed',

                    'started_at' =>
                        '2026-07-30 06:00:00',

                    'ended_at' =>
                        '2026-07-30 14:00:00',

                    'produced_quantity' =>
                        '1000.000',

                    'good_quantity' =>
                        '975.000',

                    'rejected_quantity' =>
                        '25.000',

                    'quantity_unit' =>
                        'bottles',

                    'runtime_minutes' => 450,
                    'downtime_minutes' => 30,
                ],

                ProductionRecordErpData::class,
            ],

            'run log' => [
                ErpResource::RunLogs,

                [
                    'machine_run_external_id' =>
                        'RUN-001',

                    'machine_external_id' =>
                        'MACHINE-001',

                    'log_type' =>
                        'production',

                    'message' =>
                        'Production quantity recorded.',

                    'recorded_at' =>
                        '2026-07-30 10:00:00',

                    'numeric_value' =>
                        '500.000',

                    'unit' =>
                        'bottles',
                ],

                RunLogErpData::class,
            ],

            'downtime event' => [
                ErpResource::DowntimeEvents,

                [
                    'event_number' =>
                        'DOWN-001',

                    'machine_external_id' =>
                        'MACHINE-001',

                    'production_line_external_id' =>
                        'LINE-001',

                    'batch_external_id' =>
                        'BATCH-001',

                    'shift_external_id' =>
                        'SHIFT-A',

                    'operator_external_id' =>
                        'OPERATOR-001',

                    'severity' =>
                        'warning',

                    'category' =>
                        'Mechanical interruption',

                    'reason' =>
                        'Temporary filler blockage.',

                    'started_at' =>
                        '2026-07-30 10:00:00',

                    'ended_at' =>
                        '2026-07-30 10:15:00',

                    'is_resolved' => true,
                ],

                DowntimeEventErpData::class,
            ],

            'machine status event' => [
                ErpResource::MachineStatusEvents,

                [
                    'machine_external_id' =>
                        'MACHINE-001',

                    'status' =>
                        'fault',

                    'occurred_at' =>
                        '2026-07-30 10:00:00',

                    'ended_at' =>
                        '2026-07-30 10:15:00',

                    'reason' =>
                        'Synthetic fault event.',
                ],

                MachineStatusEventErpData::class,
            ],

            'maintenance history' => [
                ErpResource::MaintenanceHistory,

                [
                    'maintenance_number' =>
                        'MAINT-001',

                    'machine_external_id' =>
                        'MACHINE-001',

                    'maintenance_type' =>
                        'corrective',

                    'status' =>
                        'completed',

                    'scheduled_at' =>
                        '2026-07-30 10:00:00',

                    'started_at' =>
                        '2026-07-30 10:05:00',

                    'completed_at' =>
                        '2026-07-30 11:00:00',

                    'performed_by_external_id' =>
                        'TECHNICIAN-001',

                    'description' =>
                        'Repair filler blockage.',

                    'actions_taken' =>
                        'Cleaned and adjusted filling head.',

                    'downtime_minutes' => 55,

                    'cost' => '350.00',

                    'currency' => 'MAD',
                ],

                MaintenanceHistoryErpData::class,
            ],

            'inspection' => [
                ErpResource::Inspections,

                [
                    'inspection_number' =>
                        'INS-001',

                    'batch_external_id' =>
                        'BATCH-001',

                    'finished_lot_external_id' =>
                        'LOT-001',

                    'inspector_external_id' =>
                        'INSPECTOR-001',

                    'inspection_type' =>
                        'Finished-product inspection',

                    'result' =>
                        'passed',

                    'inspected_at' =>
                        '2026-07-30 15:00:00',

                    'sample_size' => 100,
                    'passed_quantity' => 100,
                    'failed_quantity' => 0,

                    'notes' =>
                        'All inspected samples passed.',
                ],

                InspectionErpData::class,
            ],

            'nonconformity' => [
                ErpResource::Nonconformities,

                [
                    'nonconformity_number' =>
                        'NC-001',

                    'inspection_external_id' =>
                        'INSPECTION-001',

                    'batch_external_id' =>
                        'BATCH-001',

                    'severity' =>
                        'minor',

                    'status' =>
                        'corrected',

                    'category' =>
                        'Label alignment',

                    'description' =>
                        'Minor label alignment deviation.',

                    'detected_at' =>
                        '2026-07-30 15:00:00',

                    'corrected_at' =>
                        '2026-07-30 15:20:00',

                    'corrective_action' =>
                        'Adjusted label applicator.',
                ],

                NonconformityErpData::class,
            ],

            'finished lot' => [
                ErpResource::FinishedLots,

                [
                    'lot_number' =>
                        'LOT-001',

                    'batch_external_id' =>
                        'BATCH-001',

                    'product_external_id' =>
                        'PRODUCT-001',

                    'status' =>
                        'released',

                    'produced_at' =>
                        '2026-07-30 14:00:00',

                    'expiry_date' =>
                        '2027-07-30',

                    'produced_quantity' =>
                        '1000.000',

                    'released_quantity' =>
                        '975.000',

                    'rejected_quantity' =>
                        '25.000',

                    'quantity_unit' =>
                        'bottles',

                    'released_at' =>
                        '2026-07-30 16:00:00',

                    'released_by_external_id' =>
                        'QUALITY-MANAGER-001',

                    'release_notes' =>
                        'Released after quality approval.',
                ],

                FinishedLotErpData::class,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function record(
        ErpResource $resource,
        string $externalId,
        array $attributes
    ): ErpSourceRecord {
        return new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem:
                        'simulated_sage',

                    resource:
                        $resource,

                    externalId:
                        $externalId
                ),

            attributes:
                $attributes,

            sourceVersion: 4,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-30 18:00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-30 18:00:02'
                )
        );
    }
}
