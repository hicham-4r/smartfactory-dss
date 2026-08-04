<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\Enums\ERP\ErpResource;
use App\Services\ERP\Sync\ErpSyncTargetRegistry;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SimulatedSageMaintenancePayloadContractTest extends TestCase
{
    public function test_nested_machine_status_payload_maps_to_local_contract(): void
    {
        $mapped = app(
            ErpRecordMapperInterface::class
        )->map(
            $this->record(
                resource:
                    ErpResource::MachineStatusEvents,

                externalId:
                    'STATUS-EVENT-001',

                attributes: [
                    'status_event_number' =>
                        'STS-001',

                    'status_code' =>
                        'running',

                    'started_at' =>
                        '2026-07-24T04:21:00+00:00',

                    'ended_at' =>
                        '2026-07-24T06:00:00+00:00',

                    'notes' =>
                        'Synthetic machine status.',

                    'machine' => [
                        'external_id' =>
                            'MACHINE-001',
                    ],
                ]
            )
        );

        $data =
            $mapped->toArray()['data'];

        $this->assertSame(
            'MACHINE-001',
            $data['machine_external_id']
        );

        $this->assertSame(
            'running',
            $data['status']
        );

        $this->assertSame(
            '2026-07-24T04:21:00+00:00',
            $data['occurred_at']
        );

        $this->assertSame(
            'Synthetic machine status.',
            $data['reason']
        );
    }

    public function test_simulator_machine_status_aliases_map_to_supported_local_statuses(): void
    {
        $cases = [
            'producing' => 'running',
            'standby' => 'idle',
            'setup' => 'stopped',
            'changeover' => 'stopped',
            'cleaning' => 'stopped',
            'quality_hold' => 'stopped',
            'material_shortage' => 'stopped',
            'utility_failure' => 'stopped',
            'breakdown' => 'fault',
            'preventive_maintenance' => 'maintenance',
            'corrective_maintenance' => 'maintenance',
            'shutdown' => 'offline',
        ];

        foreach ($cases as $sourceStatus => $expectedStatus) {
            $mapped = app(
                ErpRecordMapperInterface::class
            )->map(
                $this->record(
                    resource:
                        ErpResource::MachineStatusEvents,

                    externalId:
                        'STATUS-'.strtoupper($sourceStatus),

                    attributes: [
                        'status_code' =>
                            $sourceStatus,

                        'started_at' =>
                            '2026-07-24T04:21:00+00:00',

                        'ended_at' =>
                            '2026-07-24T04:31:00+00:00',

                        'machine' => [
                            'external_id' =>
                                'MACHINE-001',
                        ],
                    ]
                )
            );

            $this->assertSame(
                $expectedStatus,
                $mapped->toArray()['data']['status'],
                'Unexpected mapping for source status: '
                .$sourceStatus
            );
        }
    }

    public function test_planned_downtime_uses_information_severity_backing_value(): void
    {
        $mapped = app(
            ErpRecordMapperInterface::class
        )->map(
            $this->record(
                resource:
                    ErpResource::DowntimeEvents,

                externalId:
                    'DOWNTIME-INFORMATION-001',

                attributes: [
                    'event_number' =>
                        'DT-INFORMATION-001',

                    'category' =>
                        'planned',

                    'downtime_type' =>
                        'cleaning',

                    'started_at' =>
                        '2026-07-24T02:40:00+00:00',

                    'ended_at' =>
                        '2026-07-24T03:00:00+00:00',

                    'duration_minutes' =>
                        20,

                    'status' =>
                        'resolved',

                    'machine' => [
                        'external_id' =>
                            'MACHINE-001',
                    ],

                    'production_line' => [
                        'external_id' =>
                            'LINE-001',
                    ],

                    'shift' => [
                        'external_id' =>
                            'SHIFT-001',
                    ],

                    'production_batch' => [
                        'external_id' =>
                            'BATCH-001',
                    ],
                ]
            )
        );

        $this->assertSame(
            'information',
            $mapped->toArray()['data']['severity']
        );
    }

    public function test_nested_downtime_payload_maps_to_unified_production_event_contract(): void
    {
        $mapped = app(
            ErpRecordMapperInterface::class
        )->map(
            $this->record(
                resource:
                    ErpResource::DowntimeEvents,

                externalId:
                    'DOWNTIME-001',

                attributes: [
                    'event_number' =>
                        'DT-001',

                    'category' =>
                        'unplanned',

                    'downtime_type' =>
                        'breakdown',

                    'reason_code' =>
                        'BRK-001',

                    'reason_description' =>
                        'Synthetic unexpected machine breakdown.',

                    'started_at' =>
                        '2026-07-24T02:40:00+00:00',

                    'ended_at' =>
                        '2026-07-24T04:21:00+00:00',

                    'duration_minutes' =>
                        101,

                    'status' =>
                        'resolved',

                    'machine' => [
                        'external_id' =>
                            'MACHINE-001',
                    ],

                    'production_line' => [
                        'external_id' =>
                            'LINE-001',
                    ],

                    'shift' => [
                        'external_id' =>
                            'SHIFT-001',
                    ],

                    'production_batch' => [
                        'external_id' =>
                            'BATCH-001',
                    ],
                ]
            )
        );

        $data =
            $mapped->toArray()['data'];

        $this->assertSame(
            'downtime',
            $data['event_type']
        );

        $this->assertSame(
            'Downtime - Breakdown',
            $data['title']
        );

        $this->assertSame(
            '[BRK-001] Synthetic unexpected machine breakdown.',
            $data['description']
        );

        $this->assertSame(
            'critical',
            $data['severity']
        );

        $this->assertSame(
            'MACHINE-001',
            $data['machine_external_id']
        );

        $this->assertSame(
            'LINE-001',
            $data['production_line_external_id']
        );

        $this->assertSame(
            'BATCH-001',
            $data['batch_external_id']
        );

        $this->assertSame(
            'SHIFT-001',
            $data['shift_external_id']
        );

        $this->assertTrue(
            $data['is_resolved']
        );

        $this->assertSame(
            '2026-07-24T04:21:00+00:00',
            $data['resolved_at']
        );
    }

    public function test_nested_maintenance_payload_maps_cost_and_repair_fields(): void
    {
        $mapped = app(
            ErpRecordMapperInterface::class
        )->map(
            $this->record(
                resource:
                    ErpResource::MaintenanceHistory,

                externalId:
                    'MAINTENANCE-001',

                attributes: [
                    'maintenance_number' =>
                        'MNT-001',

                    'maintenance_type' =>
                        'corrective',

                    'status' =>
                        'completed',

                    'reported_at' =>
                        '2026-07-24T02:30:00+00:00',

                    'started_at' =>
                        '2026-07-24T02:40:00+00:00',

                    'completed_at' =>
                        '2026-07-24T04:21:00+00:00',

                    'repair_duration_minutes' =>
                        101,

                    'failure_description' =>
                        'Synthetic equipment failure.',

                    'actions_taken' =>
                        'Inspection, repair, adjustment, and restart testing.',

                    'costs' => [
                        'total_cost' =>
                            '2120.43',

                        'currency_code' =>
                            'MAD',
                    ],

                    'machine' => [
                        'external_id' =>
                            'MACHINE-001',
                    ],
                ]
            )
        );

        $data =
            $mapped->toArray()['data'];

        $this->assertSame(
            'MACHINE-001',
            $data['machine_external_id']
        );

        $this->assertSame(
            '2026-07-24T02:30:00+00:00',
            $data['scheduled_at']
        );

        $this->assertSame(
            101,
            $data['downtime_minutes']
        );

        $this->assertSame(
            '2120.43',
            $data['cost']
        );

        $this->assertSame(
            'MAD',
            $data['currency']
        );

        $this->assertSame(
            'Synthetic equipment failure.',
            $data['description']
        );

        $this->assertNull(
            $data['performed_by_external_id']
        );
    }

    public function test_downtime_persistence_requires_batch_line_and_machine_dependencies(): void
    {
        $relationships = app(
            ErpSyncTargetRegistry::class
        )->relationships(
            ErpResource::DowntimeEvents
        );

        $requiredTargets = [];

        foreach ($relationships as $relationship) {
            if (! $relationship['required']) {
                continue;
            }

            $requiredTargets[] = [
                'columns' =>
                    $relationship[
                        'target_columns'
                    ],

                'resource' =>
                    $relationship[
                        'target_resource'
                    ]->value,
            ];
        }

        $this->assertContains(
            [
                'columns' => [
                    'production_batch_id',
                    'batch_id',
                ],
                'resource' =>
                    'batches',
            ],
            $requiredTargets
        );

        $this->assertContains(
            [
                'columns' => [
                    'production_line_id',
                ],
                'resource' =>
                    'production_lines',
            ],
            $requiredTargets
        );

        $this->assertContains(
            [
                'columns' => [
                    'machine_id',
                ],
                'resource' =>
                    'machines',
            ],
            $requiredTargets
        );
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

            sourceVersion:
                1,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-24T06:06:00+00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-24T06:06:02+00:00'
                )
        );
    }
}
