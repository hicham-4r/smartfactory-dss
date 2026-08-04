<?php

namespace Tests\Feature\Api;

use App\Models\ErpMachine;
use App\Models\ErpOperator;
use App\Models\ErpPackagingFormat;
use App\Models\ErpProduct;
use App\Models\ErpProductFamily;
use App\Models\ErpProductionLine;
use App\Models\ErpShift;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpMasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-erp-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' => self::API_TOKEN,
        ]);

        /*
         * Only seed the reference and master-data tables.
         *
         * Do not run DatabaseSeeder here because it would also create
         * production, maintenance, downtime, and quality history.
         */
        $this->seed([
            ErpReferenceDataSeeder::class,
            ErpOperationalMasterDataSeeder::class,
            ErpProductCatalogSeeder::class,
        ]);
    }

    /**
     * Return the headers required by the simulated ERP API.
     *
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-ERP-Token' => self::API_TOKEN,
            'Accept' => 'application/json',
        ];
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
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
    }

    public function test_master_data_endpoints_require_api_token(): void
    {
        $protectedEndpoints = [
            '/api/products',
            '/api/production-lines',
            '/api/machines',
            '/api/shifts',
            '/api/operators',
        ];

        foreach ($protectedEndpoints as $endpoint) {
            $this->getJson($endpoint)
                ->assertUnauthorized();

            $this->withHeaders([
                'X-ERP-Token' => 'wrong-token',
                'Accept' => 'application/json',
            ])
                ->getJson($endpoint)
                ->assertUnauthorized();
        }
    }

    public function test_products_endpoint_returns_paginated_data(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/products?per_page=5');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 17)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'code',
                        'name',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertSame(
            17,
            ErpProduct::query()->count()
        );
    }

    public function test_products_can_be_filtered_by_family_code(): void
    {
        $family = ErpProductFamily::query()
            ->whereHas('products')
            ->firstOrFail();

        $expectedCount = ErpProduct::query()
            ->whereHas(
                'family',
                fn ($query) => $query->where(
                    'code',
                    $family->code
                )
            )
            ->count();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?family_code='
                . urlencode($family->code)
                . '&per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                $expectedCount
            );

        foreach ($response->json('data') as $product) {
            $this->assertSame(
                $family->code,
                $product['family']['code']
            );
        }
    }

    public function test_products_can_be_filtered_by_packaging_format(): void
    {
        $format = ErpPackagingFormat::query()
            ->whereHas('products')
            ->firstOrFail();

        $expectedCount = ErpProduct::query()
            ->whereHas(
                'packagingFormat',
                fn ($query) => $query->where(
                    'code',
                    $format->code
                )
            )
            ->count();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?format_code='
                . urlencode($format->code)
                . '&per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                $expectedCount
            );

        foreach ($response->json('data') as $product) {
            $this->assertSame(
                $format->code,
                $product['packaging_format']['code']
            );
        }
    }

    public function test_production_lines_endpoint_returns_three_lines(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/production-lines?per_page=100');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'code',
                        'name',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertSame(
            3,
            ErpProductionLine::query()->count()
        );
    }

    public function test_machines_endpoint_returns_twenty_one_machines(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/machines?per_page=100');

        $response
            ->assertOk()
            ->assertJsonCount(21, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'code',
                        'name',
                        'machine_type',
                        'criticality',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertSame(
            21,
            ErpMachine::query()->count()
        );
    }

    public function test_machines_can_be_filtered_by_machine_type(): void
    {
        $machineType = ErpMachine::query()
            ->whereNotNull('machine_type')
            ->value('machine_type');

        $this->assertNotNull($machineType);

        $expectedCount = ErpMachine::query()
            ->where('machine_type', $machineType)
            ->count();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/machines'
                . '?machine_type='
                . urlencode($machineType)
                . '&per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                $expectedCount
            );

        foreach ($response->json('data') as $machine) {
            $this->assertSame(
                $machineType,
                $machine['machine_type']
            );
        }
    }

    public function test_machines_can_be_filtered_by_line_code(): void
    {
        $line = ErpProductionLine::query()
            ->withCount('machines')
            ->firstOrFail();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/machines'
                . '?line_code='
                . urlencode($line->code)
                . '&per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                $line->machines_count
            );

        foreach ($response->json('data') as $machine) {
            $lineCodes = collect(
                $machine['production_lines'] ?? []
            )->pluck('code');

            $this->assertTrue(
                $lineCodes->contains($line->code)
            );
        }
    }

    public function test_shifts_endpoint_returns_three_shifts(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/shifts?per_page=100');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'code',
                        'name',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertSame(
            3,
            ErpShift::query()->count()
        );
    }

    public function test_operators_endpoint_returns_eighteen_operators(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/operators?per_page=100');

        $response
            ->assertOk()
            ->assertJsonCount(18, 'data')
            ->assertJsonPath('meta.total', 18)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $this->assertSame(
            18,
            ErpOperator::query()->count()
        );
    }

    public function test_search_filter_returns_matching_products(): void
    {
        $product = ErpProduct::query()
            ->firstOrFail();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?search='
                . urlencode($product->code)
                . '&per_page=100'
            );

        $response->assertOk();

        $this->assertGreaterThan(
            0,
            $response->json('meta.total')
        );

        $returnedCodes = collect(
            $response->json('data')
        )->pluck('code');

        $this->assertTrue(
            $returnedCodes->contains($product->code)
        );
    }

    public function test_updated_since_can_return_an_empty_result(): void
    {
        $endpoints = [
            '/api/products',
            '/api/production-lines',
            '/api/machines',
            '/api/shifts',
            '/api/operators',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this
                ->withHeaders($this->apiHeaders())
                ->getJson(
                    $endpoint
                    . '?updated_since='
                    . '2099-01-01T00%3A00%3A00Z'
                );

            $response
                ->assertOk()
                ->assertJsonCount(0, 'data')
                ->assertJsonPath('meta.total', 0);
        }
    }

    public function test_invalid_pagination_returns_validation_error(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/products?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/machines?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }
}