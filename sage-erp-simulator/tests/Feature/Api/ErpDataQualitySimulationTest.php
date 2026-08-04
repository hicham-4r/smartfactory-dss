<?php

namespace Tests\Feature\Api;

use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpDataQualitySimulationTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN = 'test-erp-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' => self::API_TOKEN,
            'erp_data_quality.enabled' => true,
            'erp_data_quality.maximum_rate' => 100,
        ]);

        $this->seed([
            ErpReferenceDataSeeder::class,
            ErpOperationalMasterDataSeeder::class,
            ErpProductCatalogSeeder::class,
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

    public function test_clean_scenario_does_not_change_rows(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?per_page=10'
                . '&dq_scenario=clean'
                . '&dq_seed=1001'
            );

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath(
                'meta.data_quality.scenario',
                'clean'
            )
            ->assertJsonPath(
                'meta.data_quality.database_modified',
                false
            )
            ->assertJsonPath(
                'meta.data_quality.missing_values_applied',
                0
            )
            ->assertJsonPath(
                'meta.data_quality.duplicate_rows_applied',
                0
            );

        foreach ($response->json('data') as $product) {
            $this->assertNotNull(
                $product['name']
            );
        }
    }

    public function test_missing_scenario_can_null_selected_fields(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?per_page=10'
                . '&dq_scenario=missing'
                . '&dq_missing_rate=100'
                . '&dq_fields=name'
                . '&dq_seed=1001'
            );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Data-Quality-Scenario',
                'missing'
            )
            ->assertJsonCount(10, 'data')
            ->assertJsonPath(
                'meta.data_quality.scenario',
                'missing'
            )
            ->assertJsonPath(
                'meta.data_quality.missing_rate_percent',
                100
            )
            ->assertJsonPath(
                'meta.data_quality.missing_values_applied',
                10
            );

        foreach ($response->json('data') as $product) {
            $this->assertNull(
                $product['name']
            );

            /*
             * The business identifier remains protected.
             */
            $this->assertNotNull(
                $product['code']
            );

            $this->assertNotNull(
                $product['external_id']
            );
        }
    }

    public function test_duplicate_scenario_creates_exact_duplicates(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?per_page=10'
                . '&dq_scenario=duplicates'
                . '&dq_duplicate_rate=30'
                . '&dq_seed=1001'
            );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Data-Quality-Scenario',
                'duplicates'
            )
            ->assertJsonCount(10, 'data')
            ->assertJsonPath(
                'meta.data_quality.duplicate_rows_applied',
                3
            )
            ->assertJsonPath(
                'meta.data_quality.page_size_before',
                10
            )
            ->assertJsonPath(
                'meta.data_quality.page_size_after',
                10
            );

        $encodedRows = collect(
            $response->json('data')
        )->map(
            fn (array $row): string =>
                json_encode(
                    $row,
                    JSON_THROW_ON_ERROR
                )
        );

        $this->assertLessThan(
            $encodedRows->count(),
            $encodedRows->unique()->count()
        );
    }

    public function test_same_seed_returns_same_simulated_data(): void
    {
        $url = '/api/products'
            . '?per_page=10'
            . '&dq_scenario=mixed'
            . '&dq_missing_rate=30'
            . '&dq_duplicate_rate=20'
            . '&dq_fields=name,flavor'
            . '&dq_seed=4242';

        $firstResponse = $this
            ->withHeaders($this->apiHeaders())
            ->getJson($url)
            ->assertOk();

        $secondResponse = $this
            ->withHeaders($this->apiHeaders())
            ->getJson($url)
            ->assertOk();

        $this->assertSame(
            $firstResponse->json('data'),
            $secondResponse->json('data')
        );

        $this->assertSame(
            $firstResponse->json(
                'meta.data_quality'
            ),
            $secondResponse->json(
                'meta.data_quality'
            )
        );
    }

    public function test_unsupported_missing_field_is_rejected(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?dq_scenario=missing'
                . '&dq_fields=external_id'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dq_fields',
            ]);
    }

    public function test_invalid_rates_are_rejected(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?dq_scenario=missing'
                . '&dq_missing_rate=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dq_missing_rate',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?dq_scenario=duplicates'
                . '&dq_duplicate_rate=-1'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'dq_duplicate_rate',
            ]);
    }

    public function test_request_without_scenario_is_unchanged(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson('/api/products?per_page=5');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->assertNull(
            $response->json(
                'meta.data_quality'
            )
        );
    }
}