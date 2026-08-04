<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\MachineErpData;
use App\DTOs\ERP\Mapped\OperatorAssignmentErpData;
use App\DTOs\ERP\Mapped\OperatorErpData;
use App\DTOs\ERP\Mapped\ProductErpData;
use App\DTOs\ERP\Mapped\ProductFamilyErpData;
use App\DTOs\ERP\Mapped\ProductionLineErpData;
use App\DTOs\ERP\Mapped\ShiftErpData;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpMappingException;
use App\Services\ERP\Mapping\SimulatedSageRecordMapper;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErpMasterDataMappingTest extends TestCase
{
    public function test_service_container_resolves_simulated_sage_mapper(): void
    {
        $mapper = app(
            ErpRecordMapperInterface::class
        );

        $this->assertInstanceOf(
            SimulatedSageRecordMapper::class,
            $mapper
        );

        $this->assertSame(
            'simulated_sage',
            $mapper->sourceSystem()
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param class-string<ErpMappedEntityInterface> $expectedClass
     */
    #[DataProvider('masterDataProvider')]
    public function test_master_data_resources_are_mapped_to_typed_dtos(
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

    public function test_mapper_accepts_documented_payload_aliases(): void
    {
        $record = $this->record(
            resource:
                ErpResource::Products,

            externalId:
                'PRODUCT-ALIAS',

            attributes: [
                'product_code' =>
                    'VAL-ORA-1L',

                'designation' =>
                    'Valencia Orange 1 L',

                'family_id' =>
                    15,

                'stock_code' =>
                    'SKU-ORANGE-1L',

                'uom' =>
                    'bottles',

                'enabled' =>
                    'yes',
            ]
        );

        $mapped = app(
            ErpRecordMapperInterface::class
        )->map($record);

        $this->assertInstanceOf(
            ProductErpData::class,
            $mapped
        );

        $this->assertSame(
            'VAL-ORA-1L',
            $mapped->code
        );

        $this->assertSame(
            '15',
            $mapped
                ->productFamilyExternalId
        );

        $this->assertSame(
            'bottles',
            $mapped->quantityUnit
        );

        $this->assertTrue(
            $mapped->isActive
        );
    }

    public function test_missing_required_field_is_rejected_without_exposing_payload(): void
    {
        $record = $this->record(
            resource:
                ErpResource::Machines,

            externalId:
                'MACHINE-MISSING-LINE',

            attributes: [
                'code' =>
                    'FILL-01',

                'name' =>
                    'Filler 01',

                /*
                 * production_line_external_id
                 * is intentionally missing.
                 */
                'internal_secret' =>
                    'must-not-appear',
            ]
        );

        try {
            app(
                ErpRecordMapperInterface::class
            )->map($record);

            $this->fail(
                'The invalid ERP record was accepted.'
            );
        } catch (
            ErpMappingException $exception
        ) {
            $this->assertStringContainsString(
                'production_line_external_id',
                $exception->getMessage()
            );

            $this->assertStringNotContainsString(
                'must-not-appear',
                $exception->getMessage()
            );

            $this->assertArrayNotHasKey(
                'internal_secret',
                $exception->context()
            );

            $this->assertSame(
                'MACHINE-MISSING-LINE',
                $exception
                    ->context()['external_id']
            );
        }
    }

    public function test_invalid_boolean_value_is_rejected(): void
    {
        $record = $this->record(
            resource:
                ErpResource::ProductionLines,

            externalId:
                'LINE-INVALID',

            attributes: [
                'code' =>
                    'LINE-01',

                'name' =>
                    'Line 01',

                'is_active' =>
                    'sometimes',
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_invalid_operator_email_is_rejected(): void
    {
        $record = $this->record(
            resource:
                ErpResource::Operators,

            externalId:
                'OPERATOR-INVALID-EMAIL',

            attributes: [
                'employee_number' =>
                    'EMP-001',

                'name' =>
                    'Synthetic Operator',

                'email' =>
                    'not-an-email',
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_assignment_end_cannot_precede_start(): void
    {
        $record = $this->record(
            resource:
                ErpResource::OperatorAssignments,

            externalId:
                'ASSIGNMENT-INVALID',

            attributes: [
                'operator_id' =>
                    'OP-001',

                'line_id' =>
                    'LINE-01',

                'shift_id' =>
                    'SHIFT-01',

                'valid_from' =>
                    '2026-07-15 08:00:00',

                'valid_until' =>
                    '2026-07-14 08:00:00',
            ]
        );

        $this->expectException(
            ErpMappingException::class
        );

        app(
            ErpRecordMapperInterface::class
        )->map($record);
    }

    public function test_overnight_shift_is_detected(): void
    {
        $record = $this->record(
            resource:
                ErpResource::Shifts,

            externalId:
                'SHIFT-NIGHT',

            attributes: [
                'code' =>
                    'NIGHT',

                'name' =>
                    'Night shift',

                'start_time' =>
                    '22:00:00',

                'end_time' =>
                    '06:00:00',

                'is_active' =>
                    true,
            ]
        );

        $mapped = app(
            ErpRecordMapperInterface::class
        )->map($record);

        $this->assertInstanceOf(
            ShiftErpData::class,
            $mapped
        );

        $this->assertTrue(
            $mapped->crossesMidnight
        );
    }

    public function test_mapper_rejects_records_from_another_source_system(): void
    {
        $record = new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem:
                        'another_erp',

                    resource:
                        ErpResource::Products,

                    externalId:
                        'PRODUCT-OTHER-ERP'
                ),

            attributes: [
                'code' =>
                    'OTHER-PRODUCT',

                'name' =>
                    'Other ERP Product',

                'product_family_external_id' =>
                    'OTHER-FAMILY',
            ],

            sourceVersion: 1,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-30 12:00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-30 12:00:02'
                )
        );

        $mapper = app(
            ErpRecordMapperInterface::class
        );

        $this->assertFalse(
            $mapper->supports($record)
        );

        $this->expectException(
            ErpMappingException::class
        );

        $mapper->map($record);
    }

    /**
     * @return array<string, array{
     *     0: ErpResource,
     *     1: array<string, mixed>,
     *     2: class-string<ErpMappedEntityInterface>
     * }>
     */
    public static function masterDataProvider(): array
    {
        return [
            'product family' => [
                ErpResource::ProductFamilies,

                [
                    'code' =>
                        'PREMIUM',

                    'name' =>
                        'Valencia Premium',

                    'description' =>
                        'Premium pure juices.',

                    'is_active' =>
                        true,
                ],

                ProductFamilyErpData::class,
            ],

            'product' => [
                ErpResource::Products,

                [
                    'code' =>
                        'VAL-ORA-1L',

                    'name' =>
                        'Valencia Orange 1 L',

                    'product_family_external_id' =>
                        'FAMILY-PREMIUM',

                    'sku' =>
                        'SKU-VAL-ORA-1L',

                    'quantity_unit' =>
                        'bottles',

                    'is_active' =>
                        true,
                ],

                ProductErpData::class,
            ],

            'production line' => [
                ErpResource::ProductionLines,

                [
                    'code' =>
                        'LINE-01',

                    'name' =>
                        'Pasteurisation and Filling Line 01',

                    'description' =>
                        'Synthetic line.',

                    'is_active' =>
                        true,
                ],

                ProductionLineErpData::class,
            ],

            'machine' => [
                ErpResource::Machines,

                [
                    'code' =>
                        'FILL-01',

                    'name' =>
                        'Filling Machine 01',

                    'production_line_external_id' =>
                        'LINE-01',

                    'machine_type' =>
                        'filler',

                    'manufacturer' =>
                        'Synthetic Manufacturer',

                    'model' =>
                        'SIM-F100',

                    'is_active' =>
                        true,
                ],

                MachineErpData::class,
            ],

            'shift' => [
                ErpResource::Shifts,

                [
                    'code' =>
                        'SHIFT-A',

                    'name' =>
                        'Morning shift',

                    'start_time' =>
                        '06:00:00',

                    'end_time' =>
                        '14:00:00',

                    'is_active' =>
                        true,
                ],

                ShiftErpData::class,
            ],

            'operator' => [
                ErpResource::Operators,

                [
                    'employee_number' =>
                        'EMP-001',

                    'name' =>
                        'Synthetic Operator 001',

                    'email' =>
                        'operator001@example.test',

                    'job_title' =>
                        'Line operator',

                    'is_active' =>
                        true,
                ],

                OperatorErpData::class,
            ],

            'operator assignment' => [
                ErpResource::OperatorAssignments,

                [
                    'operator_external_id' =>
                        'OPERATOR-001',

                    'production_line_external_id' =>
                        'LINE-01',

                    'shift_external_id' =>
                        'SHIFT-A',

                    'valid_from' =>
                        '2026-07-01 00:00:00',

                    'valid_until' =>
                        null,

                    'is_active' =>
                        true,
                ],

                OperatorAssignmentErpData::class,
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

            sourceVersion: 3,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-30 12:00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-30 12:00:02'
                )
        );
    }
}