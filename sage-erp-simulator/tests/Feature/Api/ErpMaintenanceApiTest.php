<?php

namespace Tests\Feature\Api;

use App\Models\ErpDowntimeEvent;
use App\Models\ErpMachineStatusEvent;
use App\Models\ErpMaintenanceHistory;
use Database\Seeders\ErpMaintenanceDowntimeDataSeeder;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductionOperationalDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-erp-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' => self::API_TOKEN,
        ]);

        $this->seed([
            ErpReferenceDataSeeder::class,
            ErpOperationalMasterDataSeeder::class,
            ErpProductCatalogSeeder::class,
            ErpProductionOperationalDataSeeder::class,
            ErpMaintenanceDowntimeDataSeeder::class,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-ERP-Token' => self::API_TOKEN,
            'Accept' => 'application/json',
        ];
    }

    public function test_maintenance_endpoints_filters_and_integrity(): void
    {
        $this->assertGreaterThan(
            0,
            ErpDowntimeEvent::query()->count()
        );

        $this->assertGreaterThan(
            5670,
            ErpMachineStatusEvent::query()->count()
        );

        $this->assertGreaterThan(
            0,
            ErpMaintenanceHistory::query()->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Downtime events
        |--------------------------------------------------------------------------
        */

        $downtime = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/downtime-events?per_page=5');

        $downtime
            ->assertOk()
            ->assertHeader(
                'X-Data-Source',
                'simulated'
            )
            ->assertJsonCount(5, 'data')
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'event_number',
                        'category',
                        'downtime_type',
                        'reason_code',
                        'started_at',
                        'ended_at',
                        'duration_minutes',
                        'production_impact_units',
                        'status',
                        'is_late_arrival',
                        'source_updated_at',
                        'machine',
                        'production_line',
                        'shift',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach ($downtime->json('data') as $event) {
            $this->assertGreaterThan(
                0,
                $event['duration_minutes']
            );
        }

        $breakdowns = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/downtime-events'
                . '?downtime_type=breakdown'
                . '&per_page=100'
            );

        $breakdowns->assertOk();

        $this->assertGreaterThan(
            0,
            $breakdowns->json('meta.total')
        );

        foreach ($breakdowns->json('data') as $event) {
            $this->assertSame(
                'breakdown',
                $event['downtime_type']
            );

            $this->assertSame(
                'unplanned',
                $event['category']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Machine status events
        |--------------------------------------------------------------------------
        */

        $statusEvents = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/machine-status-events?per_page=5'
            );

        $statusEvents
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'status_event_number',
                        'status_code',
                        'started_at',
                        'ended_at',
                        'duration_minutes',
                        'is_late_arrival',
                        'source_updated_at',
                        'machine',
                        'production_line',
                        'shift',
                    ],
                ],
                'links',
                'meta',
            ]);

        $stoppedEvents = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/machine-status-events'
                . '?status_code=stopped'
                . '&per_page=100'
            );

        $stoppedEvents->assertOk();

        $this->assertGreaterThan(
            0,
            $stoppedEvents->json('meta.total')
        );

        foreach ($stoppedEvents->json('data') as $event) {
            $this->assertSame(
                'stopped',
                $event['status_code']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Maintenance history
        |--------------------------------------------------------------------------
        */

        $maintenance = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/maintenance-history?per_page=5'
            );

        $maintenance
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'maintenance_number',
                        'maintenance_type',
                        'priority',
                        'status',
                        'reported_at',
                        'started_at',
                        'completed_at',
                        'repair_duration_minutes',
                        'costs' => [
                            'parts_cost',
                            'labor_cost',
                            'total_cost',
                            'currency_code',
                        ],
                        'machine',
                        'production_line',
                        'downtime_event',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach ($maintenance->json('data') as $record) {
            $parts = (float) $record['costs']['parts_cost'];
            $labor = (float) $record['costs']['labor_cost'];
            $total = (float) $record['costs']['total_cost'];

            $this->assertEqualsWithDelta(
                $parts + $labor,
                $total,
                0.01
            );

            $this->assertSame(
                'MAD',
                $record['costs']['currency_code']
            );
        }

        $corrective = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/maintenance-history'
                . '?maintenance_type=corrective'
                . '&per_page=100'
            );

        $corrective->assertOk();

        $this->assertGreaterThan(
            0,
            $corrective->json('meta.total')
        );

        foreach ($corrective->json('data') as $record) {
            $this->assertSame(
                'corrective',
                $record['maintenance_type']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Late-arrival and incremental filters
        |--------------------------------------------------------------------------
        */

        $lateDowntime = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/downtime-events'
                . '?is_late_arrival=1'
                . '&per_page=100'
            );

        $lateDowntime->assertOk();

        $this->assertGreaterThan(
            0,
            $lateDowntime->json('meta.total')
        );

        foreach ($lateDowntime->json('data') as $event) {
            $this->assertTrue(
                $event['is_late_arrival']
            );
        }

        $futureSync = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/maintenance-history'
                . '?updated_since='
                . '2099-01-01T00%3A00%3A00Z'
            );

        $futureSync
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/downtime-events'
                . '?downtime_type=invalid_type'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'downtime_type',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/machine-status-events'
                . '?status_code=invalid_status'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status_code',
            ]);
    }
}