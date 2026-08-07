<?php

namespace Tests\Feature\Api;

use App\Models\ErpProductionBatch;
use App\Models\ErpProductionOrder;
use App\Models\ErpProductionRecord;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductionOperationalDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpOperationalApiTest extends TestCase
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

    /**
     * @return array<int, string>
     */
    private function allExternalIds(
        string $endpoint
    ): array {
        $firstPage = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                $endpoint
                . '?per_page=100&page=1'
            );

        $firstPage->assertOk();

        $lastPage = (int) $firstPage->json(
            'meta.last_page'
        );

        $externalIds = array_column(
            $firstPage->json('data'),
            'external_id'
        );

        for (
            $page = 2;
            $page <= $lastPage;
            $page++
        ) {
            $response = $this
                ->withHeaders($this->apiHeaders())
                ->getJson(
                    $endpoint
                    . '?per_page=100&page='
                    . $page
                );

            $response->assertOk();

            $externalIds = array_merge(
                $externalIds,
                array_column(
                    $response->json('data'),
                    'external_id'
                )
            );
        }

        return array_values($externalIds);
    }

    public function test_operational_pagination_is_stable_complete_and_unique(): void
    {
        $cases = [
            [
                'endpoint' =>
                    '/api/production-orders',

                'model' =>
                    ErpProductionOrder::class,

                'order_column' =>
                    'planned_start_at',
            ],
            [
                'endpoint' =>
                    '/api/production-batches',

                'model' =>
                    ErpProductionBatch::class,

                'order_column' =>
                    'scheduled_start_at',
            ],
            [
                'endpoint' =>
                    '/api/production-records',

                'model' =>
                    ErpProductionRecord::class,

                'order_column' =>
                    'interval_start_at',
            ],
        ];

        foreach ($cases as $case) {
            $model = $case['model'];
            $column = $case['order_column'];
            $endpoint = $case['endpoint'];

            /*
             * The fixture intentionally contains equal timestamps.
             * Offset pagination therefore requires a unique tie-breaker.
             */
            $this->assertTrue(
                $model::query()
                    ->select($column)
                    ->groupBy($column)
                    ->havingRaw('COUNT(*) > 1')
                    ->exists()
            );

            $expected = $model::query()
                ->orderByDesc($column)
                ->orderByDesc('id')
                ->pluck('external_id')
                ->all();

            $firstScan = $this->allExternalIds(
                $endpoint
            );

            $secondScan = $this->allExternalIds(
                $endpoint
            );

            $this->assertSame(
                $expected,
                $firstScan,
                "Unexpected deterministic order for {$endpoint}."
            );

            $this->assertSame(
                $firstScan,
                $secondScan,
                "Repeated pagination changed order for {$endpoint}."
            );

            $this->assertCount(
                count($expected),
                array_unique($firstScan),
                "Duplicate or omitted external IDs for {$endpoint}."
            );
        }
    }

    public function test_operational_endpoints_filters_and_integrity(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Database counts
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

        /*
        |--------------------------------------------------------------------------
        | Production orders
        |--------------------------------------------------------------------------
        */

        $orders = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/production-orders?per_page=5');

        $orders
            ->assertOk()
            ->assertHeader(
                'X-Data-Source',
                'simulated'
            )
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 270)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'order_number',
                        'planned_start_at',
                        'planned_end_at',
                        'planned_quantity',
                        'priority',
                        'status',
                        'batches_count',
                        'product' => [
                            'external_id',
                            'code',
                            'name',
                        ],
                        'production_line' => [
                            'external_id',
                            'code',
                            'name',
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        $ordersByLine = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/production-orders'
                . '?line_code=SIM_LINE_1L'
                . '&per_page=100'
            );

        $ordersByLine
            ->assertOk()
            ->assertJsonPath('meta.total', 90);

        foreach ($ordersByLine->json('data') as $order) {
            $this->assertSame(
                'SIM_LINE_1L',
                $order['production_line']['code']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Production batches
        |--------------------------------------------------------------------------
        */

        $batches = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/production-batches?per_page=5');

        $batches
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 810)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'batch_number',
                        'lot_number',
                        'planned_quantity',
                        'gross_quantity',
                        'good_quantity',
                        'rejected_quantity',
                        'status',
                        'quality_status',
                        'records_count',
                        'production_order',
                        'shift',
                    ],
                ],
                'links',
                'meta',
            ]);

        $morningBatches = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/production-batches'
                . '?shift_code=SHIFT_MORNING'
                . '&per_page=100'
            );

        $morningBatches
            ->assertOk()
            ->assertJsonPath('meta.total', 270);

        foreach ($morningBatches->json('data') as $batch) {
            $this->assertSame(
                'SHIFT_MORNING',
                $batch['shift']['code']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Production records
        |--------------------------------------------------------------------------
        */

        $records = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/production-records?per_page=5');

        $records
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 3240)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'record_number',
                        'interval_start_at',
                        'interval_end_at',
                        'target_quantity',
                        'gross_quantity',
                        'good_quantity',
                        'rejected_quantity',
                        'runtime_minutes',
                        'downtime_minutes',
                        'quality_rate_percent',
                        'is_late_arrival',
                        'source_updated_at',
                        'batch',
                        'machine',
                        'process_stage',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach ($records->json('data') as $record) {
            $this->assertSame(
                $record['gross_quantity'],
                $record['good_quantity']
                + $record['rejected_quantity']
            );
        }

        $lineRecords = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/production-records'
                . '?line_code=SIM_LINE_200ML'
                . '&per_page=100'
            );

        $lineRecords
            ->assertOk()
            ->assertJsonPath('meta.total', 1080);

        foreach ($lineRecords->json('data') as $record) {
            $this->assertSame(
                'SIM_LINE_200ML',
                $record['batch']
                    ['production_line']
                    ['code']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Late-arrival and incremental filters
        |--------------------------------------------------------------------------
        */

        $lateRecords = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/production-records'
                . '?is_late_arrival=1'
                . '&per_page=100'
            );

        $lateRecords->assertOk();

        $this->assertGreaterThan(
            0,
            $lateRecords->json('meta.total')
        );

        foreach ($lateRecords->json('data') as $record) {
            $this->assertTrue(
                $record['is_late_arrival']
            );
        }

        $futureSync = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/production-records'
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
                '/api/production-records?per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/production-orders'
                . '?date_from=2026-07-20'
                . '&date_to=2026-07-01'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    }
}