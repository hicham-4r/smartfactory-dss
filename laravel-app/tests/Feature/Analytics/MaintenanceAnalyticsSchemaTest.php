<?php

namespace Tests\Feature\Analytics;

use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\MachineStatusEventErpData;
use App\Enums\ERP\ErpMachineStatus;
use App\Enums\ERP\ErpResource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceAnalyticsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_maintenance_analytics_columns_exist(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'production_events',
                [
                    'category',
                    'downtime_type',
                    'reason_code',
                    'reason',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumn(
                'machine_status_events',
                'duration_minutes'
            )
        );
    }

    public function test_machine_status_mapping_persists_portable_duration(): void
    {
        $mapped =
            new MachineStatusEventErpData(
                source:
                    new ErpSourceRecord(
                        identity:
                            new ErpSourceIdentity(
                                sourceSystem:
                                    'simulated_sage',
                                resource:
                                    ErpResource
                                        ::MachineStatusEvents,
                                externalId:
                                    'STATUS-001'
                            ),
                        attributes: [],
                        sourceVersion: 1,
                        sourceUpdatedAt:
                            CarbonImmutable::parse(
                                '2026-08-01 08:00:00',
                                'UTC'
                            ),
                    ),
                machineExternalId:
                    'MACHINE-001',
                status:
                    ErpMachineStatus::Running,
                occurredAt:
                    CarbonImmutable::parse(
                        '2026-08-01 06:00:00',
                        'UTC'
                    ),
                endedAt:
                    CarbonImmutable::parse(
                        '2026-08-01 07:39:00',
                        'UTC'
                    ),
                reason:
                    'Synthetic status.',
            );

        $this->assertSame(
            99,
            $mapped->toArray()[
                'data'
            ]['duration_minutes']
        );
    }
}
