<?php

namespace Tests\Feature\ERP;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncGroup;
use App\Services\ERP\Sync\ErpSyncGroupRegistry;
use Tests\TestCase;

class SimulatedSageCapabilityContractTest extends TestCase
{
    public function test_external_page_size_is_limited_to_one_hundred(): void
    {
        $this->assertSame(
            100,
            config(
                'erp.connectors.simulated_sage.maximum_page_size'
            )
        );
    }

    public function test_external_connector_uses_real_operational_endpoints(): void
    {
        $endpoints = config(
            'erp.connectors.simulated_sage.endpoints'
        );

        $this->assertSame(
            '/api/production-orders',
            $endpoints['work_orders']
        );

        $this->assertSame(
            '/api/production-batches',
            $endpoints['batches']
        );

        $this->assertSame(
            '/api/production-records',
            $endpoints['machine_runs']
        );
    }

    public function test_run_logs_are_not_exposed_by_external_sage_connector(): void
    {
        $endpoints = config(
            'erp.connectors.simulated_sage.endpoints'
        );

        $this->assertArrayNotHasKey(
            'run_logs',
            $endpoints
        );
    }

    public function test_run_logs_are_registered_as_local_only_telemetry(): void
    {
        $registry = app(
            ErpSyncGroupRegistry::class
        );

        $this->assertSame(
            [
                ErpResource::RunLogs,
            ],
            $registry->localOnlyResources()
        );

        $this->assertNotContains(
            ErpResource::RunLogs,
            $registry->resources(
                ErpSyncGroup::ProductionExecution
            ),
            true
        );
    }

    public function test_production_group_contains_only_external_operational_resources(): void
    {
        $registry = app(
            ErpSyncGroupRegistry::class
        );

        $this->assertSame(
            [
                ErpResource::WorkOrders,
                ErpResource::Batches,
                ErpResource::MachineRuns,
            ],
            $registry->resources(
                ErpSyncGroup::ProductionExecution
            )
        );
    }
}