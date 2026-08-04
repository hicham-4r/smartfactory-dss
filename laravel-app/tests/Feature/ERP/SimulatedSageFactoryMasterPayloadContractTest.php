<?php

namespace Tests\Feature\ERP;

use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\Enums\ERP\ErpResource;
use App\Services\ERP\Mapping\SimulatedSageRecordMapper;
use App\Services\ERP\Sync\ErpMappedEntityPersister;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatedSageFactoryMasterPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_factory_master_payloads_map_and_persist(): void
    {
        $this->persistFactoryMasterPayloads();

        $line = $this->assertDatabaseHas(
            'production_lines',
            [
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    '57eccd3c-2934-46c4-8e54-8ac0b94111b1',

                'code' =>
                    'SIM_LINE_1L',
            ]
        );

        $this->assertDatabaseHas(
            'machines',
            [
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    'e7d0db26-341a-4582-a54c-fb191a31aa35',

                'code' =>
                    'SIM_LINE_1L_CARTONER',

                'machine_type' =>
                    'cartoning_machine',

                'model' =>
                    'SIM-CARTONER-06',

                'serial_number' =>
                    'SIM-SIM_LINE_1L-M06',

                'sequence_number' =>
                    6,

                'is_critical' =>
                    0,
            ]
        );

        $this->assertDatabaseHas(
            'shifts',
            [
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    'f4c837f0-fb7d-4e6d-b021-70d3a5ed5f85',

                'code' =>
                    'SHIFT_MORNING',

                'starts_at' =>
                    '06:00:00',

                'ends_at' =>
                    '14:00:00',

                'crosses_midnight' =>
                    0,
            ]
        );

        $this->assertDatabaseHas(
            'operators',
            [
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    '188eb2c2-5686-491c-8604-127ac73f69e4',

                'employee_code' =>
                    'SIM-OP-001',

                'first_name' =>
                    'Simulated',

                'last_name' =>
                    'Operator 001',

                'email' =>
                    'sim.operator001@example.test',

                'hired_on' =>
                    '2025-01-01',
            ]
        );

        $this->assertDatabaseHas(
            'operator_assignments',
            [
                'source_system' =>
                    'simulated_sage',

                'external_id' =>
                    'OA-001',

                'starts_on' =>
                    '2026-01-01',

                'ends_on' =>
                    null,

                'is_primary' =>
                    1,

                'is_active' =>
                    1,
            ]
        );

        $this->assertDatabaseCount(
            'production_lines',
            1
        );

        $this->assertDatabaseCount(
            'machines',
            1
        );

        $this->assertDatabaseCount(
            'shifts',
            1
        );

        $this->assertDatabaseCount(
            'operators',
            1
        );

        $this->assertDatabaseCount(
            'operator_assignments',
            1
        );
    }

    public function test_real_factory_master_payloads_persist_idempotently(): void
    {
        $this->persistFactoryMasterPayloads();
        $this->persistFactoryMasterPayloads();

        $this->assertDatabaseCount(
            'production_lines',
            1
        );

        $this->assertDatabaseCount(
            'machines',
            1
        );

        $this->assertDatabaseCount(
            'shifts',
            1
        );

        $this->assertDatabaseCount(
            'operators',
            1
        );

        $this->assertDatabaseCount(
            'operator_assignments',
            1
        );
    }

    private function persistFactoryMasterPayloads(): void
    {
        $this->persist(
            resource:
                ErpResource::ProductionLines,

            externalId:
                '57eccd3c-2934-46c4-8e54-8ac0b94111b1',

            attributes: [
                'code' =>
                    'SIM_LINE_1L',

                'name' =>
                    'Simulated 1 L Production Line',

                'description' =>
                    'Synthetic production line.',

                'is_active' =>
                    true,
            ]
        );

        $this->persist(
            resource:
                ErpResource::Machines,

            externalId:
                'e7d0db26-341a-4582-a54c-fb191a31aa35',

            attributes: [
                'code' =>
                    'SIM_LINE_1L_CARTONER',

                'name' =>
                    'Simulated 1 L Production Line - Cartoning Machine',

                'machine_type' =>
                    'cartoning_machine',

                'manufacturer' =>
                    'Simulated Equipment Manufacturer',

                'model_reference' =>
                    'SIM-CARTONER-06',

                'serial_number' =>
                    'SIM-SIM_LINE_1L-M06',

                'status' =>
                    'operational',

                'criticality' =>
                    'medium',

                'is_active' =>
                    true,

                'production_lines' => [
                    [
                        'external_id' =>
                            '57eccd3c-2934-46c4-8e54-8ac0b94111b1',

                        'code' =>
                            'SIM_LINE_1L',

                        'name' =>
                            'Simulated 1 L Production Line',

                        'sequence_order' =>
                            6,

                        'station_code' =>
                            'STATION_06',

                        'is_primary' =>
                            true,
                    ],
                ],
            ]
        );

        $this->persist(
            resource:
                ErpResource::Shifts,

            externalId:
                'f4c837f0-fb7d-4e6d-b021-70d3a5ed5f85',

            attributes: [
                'code' =>
                    'SHIFT_MORNING',

                'name' =>
                    'Morning Shift',

                'start_time' =>
                    '06:00:00',

                'end_time' =>
                    '14:00:00',

                'crosses_midnight' =>
                    false,

                'status' =>
                    'active',

                'is_active' =>
                    true,
            ]
        );

        $this->persist(
            resource:
                ErpResource::Operators,

            externalId:
                '188eb2c2-5686-491c-8604-127ac73f69e4',

            attributes: [
                'employee_code' =>
                    'SIM-OP-001',

                'first_name' =>
                    'Simulated',

                'last_name' =>
                    'Operator 001',

                'full_name' =>
                    'Simulated Operator 001',

                'email' =>
                    'sim.operator001@example.test',

                'phone' =>
                    null,

                'hire_date' =>
                    '2025-01-01',

                'status' =>
                    'active',

                'is_active' =>
                    true,
            ]
        );

        $this->persist(
            resource:
                ErpResource::OperatorAssignments,

            externalId:
                'OA-001',

            attributes: [
                'operator_external_id' =>
                    '188eb2c2-5686-491c-8604-127ac73f69e4',

                'production_line_external_id' =>
                    '57eccd3c-2934-46c4-8e54-8ac0b94111b1',

                'shift_external_id' =>
                    'f4c837f0-fb7d-4e6d-b021-70d3a5ed5f85',

                'valid_from' =>
                    '2026-01-01',

                'valid_until' =>
                    null,

                'role_on_line' =>
                    'primary_line_operator',

                'is_primary' =>
                    true,

                'is_active' =>
                    true,
            ]
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function persist(
        ErpResource $resource,
        string $externalId,
        array $attributes
    ): void {
        $source = new ErpSourceRecord(
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

            sourceVersion:
                1,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-31 20:00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-31 20:00:01'
                )
        );

        $mapped = app(
            SimulatedSageRecordMapper::class
        )->map($source);

        app(
            ErpMappedEntityPersister::class
        )->persist($mapped);
    }
}
