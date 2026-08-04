<?php

namespace Tests\Feature\Api;

use App\Models\ErpDowntimeEvent;
use App\Models\ErpFinishedLotRelease;
use App\Models\ErpMachine;
use App\Models\ErpMachineStatusEvent;
use App\Models\ErpMaintenanceHistory;
use App\Models\ErpOperator;
use App\Models\ErpPackagingFormat;
use App\Models\ErpProduct;
use App\Models\ErpProductFamily;
use App\Models\ErpProductionBatch;
use App\Models\ErpProductionLine;
use App\Models\ErpProductionOrder;
use App\Models\ErpProductionRecord;
use App\Models\ErpQualityInspection;
use App\Models\ErpQualityTestResult;
use App\Models\ErpShift;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ErpPhase3AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-erp-token';

    private const FAILURE_KEY =
        'test-failure-simulation-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' =>
                self::API_TOKEN,

            'erp_data_quality.enabled' =>
                true,

            'erp_data_quality.maximum_rate' =>
                100,

            'erp_failure_simulation.enabled' =>
                true,

            'erp_failure_simulation.key' =>
                self::FAILURE_KEY,

            'erp_failure_simulation.default_probability' =>
                100,

            'erp_failure_simulation.default_seed' =>
                20260725,

            'erp_failure_simulation.default_retry_after_seconds' =>
                30,

            'erp_failure_simulation.default_delay_ms' =>
                1,

            'erp_failure_simulation.maximum_delay_ms' =>
                100,
        ]);

        /*
         * Seed the complete simulated ERP dataset:
         *
         * - Reference data
         * - Products and production lines
         * - Production operations
         * - Downtime and maintenance
         * - Quality inspections and lot releases
         */
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Return headers used by the simulated ERP API.
     *
     * @return array<string, string>
     */
    private function apiHeaders(
        bool $withFailureKey = false
    ): array {
        $headers = [
            'X-ERP-Token' =>
                self::API_TOKEN,

            'Accept' =>
                'application/json',
        ];

        if ($withFailureKey) {
            $headers['X-ERP-Failure-Key'] =
                self::FAILURE_KEY;
        }

        return $headers;
    }

    public function test_phase_three_simulator_acceptance(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Public health endpoint
        |--------------------------------------------------------------------------
        */

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath(
                'status',
                'healthy'
            )
            ->assertJsonPath(
                'service',
                'sage-erp-simulator'
            )
            ->assertJsonPath(
                'data_source',
                'simulated'
            )
            ->assertJsonPath(
                'api_version',
                '1.0'
            )
            ->assertJsonStructure([
                'status',
                'service',
                'data_source',
                'api_version',
                'timestamp',
            ]);

        /*
        |--------------------------------------------------------------------------
        | 2. Protected endpoint security
        |--------------------------------------------------------------------------
        */

        $this->getJson('/api/products')
            ->assertUnauthorized();

        $this->withHeaders([
            'X-ERP-Token' => 'incorrect-token',
            'Accept' => 'application/json',
        ])
            ->getJson('/api/products')
            ->assertUnauthorized();

        /*
        |--------------------------------------------------------------------------
        | 3. Reference and master data
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            10,
            ErpProductFamily::query()->count()
        );

        $this->assertSame(
            3,
            ErpPackagingFormat::query()->count()
        );

        $this->assertSame(
            3,
            ErpProductionLine::query()->count()
        );

        $this->assertSame(
            21,
            ErpMachine::query()->count()
        );

        $this->assertSame(
            3,
            ErpShift::query()->count()
        );

        $this->assertSame(
            18,
            ErpOperator::query()->count()
        );

        $this->assertSame(
            17,
            ErpProduct::query()->count()
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Production operational data
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            270,
            ErpProductionOrder::query()->count()
        );

        $this->assertSame(
            810,
            ErpProductionBatch::query()->count()
        );

        $this->assertSame(
            3240,
            ErpProductionRecord::query()->count()
        );

        $invalidProductionRecords =
            ErpProductionRecord::query()
                ->whereRaw(
                    'gross_quantity <> '
                    . 'good_quantity + rejected_quantity'
                )
                ->count();

        $this->assertSame(
            0,
            $invalidProductionRecords,
            'Every production record must satisfy: '
            . 'gross quantity = good quantity + rejected quantity.'
        );

        /*
        |--------------------------------------------------------------------------
        | 5. Maintenance and downtime data
        |--------------------------------------------------------------------------
        */

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
         * SQLite may represent DECIMAL values internally using numeric or
         * floating-point storage. Exact SQL comparisons can therefore mark
         * mathematically equal costs as different.
         *
         * Convert each amount separately to integer centimes before comparing.
         */
        $invalidMaintenanceCosts =
            ErpMaintenanceHistory::query()
                ->get([
                    'id',
                    'maintenance_number',
                    'parts_cost',
                    'labor_cost',
                    'total_cost',
                ])
                ->filter(
                    function (
                        ErpMaintenanceHistory $maintenance
                    ): bool {
                        $partsCostCentimes = (int) round(
                            (float) $maintenance->parts_cost
                            * 100
                        );

                        $laborCostCentimes = (int) round(
                            (float) $maintenance->labor_cost
                            * 100
                        );

                        $storedTotalCentimes = (int) round(
                            (float) $maintenance->total_cost
                            * 100
                        );

                        $calculatedTotalCentimes =
                            $partsCostCentimes
                            + $laborCostCentimes;

                        return $storedTotalCentimes
                            !== $calculatedTotalCentimes;
                    }
                );

        $this->assertSame(
            0,
            $invalidMaintenanceCosts->count(),
            'Invalid maintenance costs: '
            . $invalidMaintenanceCosts
                ->pluck('maintenance_number')
                ->implode(', ')
        );

        $invalidDowntimeDurations =
            ErpDowntimeEvent::query()
                ->where(
                    'duration_minutes',
                    '<=',
                    0
                )
                ->count();

        $this->assertSame(
            0,
            $invalidDowntimeDurations,
            'Every downtime event must have '
            . 'a positive duration.'
        );

        /*
        |--------------------------------------------------------------------------
        | 6. Quality inspections and finished-lot releases
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            810,
            ErpQualityInspection::query()->count()
        );

        $this->assertSame(
            4860,
            ErpQualityTestResult::query()->count()
        );

        $this->assertSame(
            810,
            ErpFinishedLotRelease::query()->count()
        );

        $batchesWithoutInspection =
            ErpProductionBatch::query()
                ->doesntHave('qualityInspection')
                ->count();

        $this->assertSame(
            0,
            $batchesWithoutInspection,
            'Every production batch must have '
            . 'one quality inspection.'
        );

        $batchesWithoutRelease =
            ErpProductionBatch::query()
                ->doesntHave('finishedLotRelease')
                ->count();

        $this->assertSame(
            0,
            $batchesWithoutRelease,
            'Every production batch must have '
            . 'one finished-lot release decision.'
        );

        $invalidTestCounts =
            ErpQualityInspection::query()
                ->withCount('testResults')
                ->get()
                ->filter(
                    fn (
                        ErpQualityInspection $inspection
                    ): bool =>
                        $inspection->test_results_count !== 6
                )
                ->count();

        $this->assertSame(
            0,
            $invalidTestCounts,
            'Every quality inspection must contain '
            . 'exactly six test results.'
        );

        $releasedWithoutCertificate =
            ErpFinishedLotRelease::query()
                ->where(
                    'decision',
                    'released'
                )
                ->whereNull(
                    'quality_certificate_number'
                )
                ->count();

        $this->assertSame(
            0,
            $releasedWithoutCertificate,
            'Every released lot must have '
            . 'a quality certificate number.'
        );

        $failedInspectionsReleased =
            ErpFinishedLotRelease::query()
                ->where(
                    'decision',
                    'released'
                )
                ->whereHas(
                    'qualityInspection',
                    fn ($query) =>
                        $query->where(
                            'result',
                            'failed'
                        )
                )
                ->count();

        $this->assertSame(
            0,
            $failedInspectionsReleased,
            'A lot with a failed quality inspection '
            . 'must never be released.'
        );

        $invalidReleasedWarehouseStatuses =
            ErpFinishedLotRelease::query()
                ->where(
                    'decision',
                    'released'
                )
                ->where(
                    'warehouse_status',
                    '<>',
                    'available'
                )
                ->count();

        $this->assertSame(
            0,
            $invalidReleasedWarehouseStatuses,
            'Every released lot must have '
            . 'the warehouse status available.'
        );

        /*
        |--------------------------------------------------------------------------
        | 7. Verify every API endpoint
        |--------------------------------------------------------------------------
        */

        $endpoints = [
            '/api/products',
            '/api/production-lines',
            '/api/machines',
            '/api/shifts',
            '/api/operators',
            '/api/production-orders',
            '/api/production-batches',
            '/api/production-records',
            '/api/downtime-events',
            '/api/machine-status-events',
            '/api/maintenance-history',
            '/api/quality-inspections',
            '/api/quality-test-results',
            '/api/finished-lot-releases',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this
                ->withHeaders(
                    $this->apiHeaders()
                )
                ->getJson(
                    $endpoint . '?per_page=1'
                );

            $response
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath(
                    'meta.data_source',
                    'simulated'
                )
                ->assertJsonStructure([
                    'data',
                    'links',
                    'meta',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Capture database counts before response simulations
        |--------------------------------------------------------------------------
        */

        $databaseSnapshot = [
            'products' =>
                ErpProduct::query()->count(),

            'production_records' =>
                ErpProductionRecord::query()->count(),

            'downtime_events' =>
                ErpDowntimeEvent::query()->count(),

            'maintenance_history' =>
                ErpMaintenanceHistory::query()->count(),

            'quality_inspections' =>
                ErpQualityInspection::query()->count(),

            'finished_lot_releases' =>
                ErpFinishedLotRelease::query()->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | 9. Missing-value simulation
        |--------------------------------------------------------------------------
        */

        $missingResponse = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/products'
                . '?per_page=5'
                . '&dq_scenario=missing'
                . '&dq_missing_rate=100'
                . '&dq_fields=name'
                . '&dq_seed=1001'
            );

        $missingResponse
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertHeader(
                'X-Data-Quality-Scenario',
                'missing'
            )
            ->assertJsonPath(
                'meta.data_quality.scenario',
                'missing'
            )
            ->assertJsonPath(
                'meta.data_quality.database_modified',
                false
            )
            ->assertJsonPath(
                'meta.data_quality.missing_values_applied',
                5
            );

        foreach (
            $missingResponse->json('data')
            as $product
        ) {
            $this->assertNull(
                $product['name']
            );

            $this->assertNotNull(
                $product['external_id']
            );

            $this->assertNotNull(
                $product['code']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Duplicate-row simulation
        |--------------------------------------------------------------------------
        */

        $duplicateResponse = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/products'
                . '?per_page=10'
                . '&dq_scenario=duplicates'
                . '&dq_duplicate_rate=40'
                . '&dq_seed=1001'
            );

        $duplicateResponse
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertHeader(
                'X-Data-Quality-Scenario',
                'duplicates'
            )
            ->assertJsonPath(
                'meta.data_quality.scenario',
                'duplicates'
            )
            ->assertJsonPath(
                'meta.data_quality.database_modified',
                false
            )
            ->assertJsonPath(
                'meta.data_quality.duplicate_rows_applied',
                4
            );

        $externalIds = collect(
            $duplicateResponse->json('data')
        )->pluck('external_id');

        $this->assertLessThan(
            $externalIds->count(),
            $externalIds->unique()->count(),
            'The duplicate simulation must return '
            . 'at least one repeated external ID.'
        );

        /*
        |--------------------------------------------------------------------------
        | 11. Artificial service-unavailable failure
        |--------------------------------------------------------------------------
        */

        $failureResponse = $this
            ->withHeaders(
                $this->apiHeaders(true)
            )
            ->getJson(
                '/api/products'
                . '?failure_scenario=service_unavailable'
                . '&failure_probability=100'
                . '&failure_seed=1001'
            );

        $failureResponse
            ->assertStatus(503)
            ->assertHeader(
                'X-Simulated-Failure-Scenario',
                'service_unavailable'
            )
            ->assertHeader(
                'X-Simulated-Failure-Triggered',
                'true'
            )
            ->assertJsonPath(
                'error_code',
                'SIMULATED_SERVICE_UNAVAILABLE'
            )
            ->assertJsonPath(
                'data_source',
                'simulated'
            )
            ->assertJsonPath(
                'failure.database_modified',
                false
            );

        /*
        |--------------------------------------------------------------------------
        | 12. Artificial slow response
        |--------------------------------------------------------------------------
        */

        $slowResponse = $this
            ->withHeaders(
                $this->apiHeaders(true)
            )
            ->getJson(
                '/api/products'
                . '?per_page=1'
                . '&failure_scenario=slow_response'
                . '&failure_probability=100'
                . '&failure_delay_ms=1'
            );

        $slowResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertHeader(
                'X-Simulated-Failure-Scenario',
                'slow_response'
            )
            ->assertHeader(
                'X-Simulated-Failure-Triggered',
                'true'
            )
            ->assertHeader(
                'X-Simulated-Delay-Milliseconds',
                '1'
            );

        /*
        |--------------------------------------------------------------------------
        | 13. Malformed-response simulation
        |--------------------------------------------------------------------------
        */

        $malformedResponse = $this
            ->withHeaders(
                $this->apiHeaders(true)
            )
            ->getJson(
                '/api/products'
                . '?failure_scenario=malformed_response'
                . '&failure_probability=100'
                . '&failure_seed=1001'
            );

        $malformedResponse
            ->assertOk()
            ->assertHeader(
                'X-Simulated-Failure-Scenario',
                'malformed_response'
            )
            ->assertHeader(
                'X-Simulated-Failure-Triggered',
                'true'
            )
            ->assertJsonPath(
                'unexpected_payload',
                'simulated-invalid-contract'
            )
            ->assertJsonPath(
                'records',
                'not-an-array'
            );

        $malformedPayload =
            $malformedResponse->json();

        $this->assertArrayNotHasKey(
            'data',
            $malformedPayload
        );

        $this->assertArrayNotHasKey(
            'links',
            $malformedPayload
        );

        /*
        |--------------------------------------------------------------------------
        | 14. Confirm simulations did not modify the database
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $databaseSnapshot['products'],
            ErpProduct::query()->count()
        );

        $this->assertSame(
            $databaseSnapshot[
                'production_records'
            ],
            ErpProductionRecord::query()->count()
        );

        $this->assertSame(
            $databaseSnapshot[
                'downtime_events'
            ],
            ErpDowntimeEvent::query()->count()
        );

        $this->assertSame(
            $databaseSnapshot[
                'maintenance_history'
            ],
            ErpMaintenanceHistory::query()->count()
        );

        $this->assertSame(
            $databaseSnapshot[
                'quality_inspections'
            ],
            ErpQualityInspection::query()->count()
        );

        $this->assertSame(
            $databaseSnapshot[
                'finished_lot_releases'
            ],
            ErpFinishedLotRelease::query()->count()
        );

        /*
        |--------------------------------------------------------------------------
        | 15. Confirm isolated SQLite testing database
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            'sqlite',
            DB::connection()->getDriverName()
        );
    }
}