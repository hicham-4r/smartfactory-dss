<?php

namespace Tests\Feature\Api;

use App\Models\ErpProduct;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpApiFailureSimulationTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN =
        'test-erp-token';

    private const FAILURE_KEY =
        'test-failure-simulation-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' =>
                self::API_TOKEN,

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

        $this->seed([
            ErpReferenceDataSeeder::class,
            ErpOperationalMasterDataSeeder::class,
            ErpProductCatalogSeeder::class,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(
        bool $includeFailureKey = true
    ): array {
        $headers = [
            'X-ERP-Token' =>
                self::API_TOKEN,

            'Accept' =>
                'application/json',
        ];

        if ($includeFailureKey) {
            $headers['X-ERP-Failure-Key'] =
                self::FAILURE_KEY;
        }

        return $headers;
    }

    public function test_normal_request_is_not_changed(): void
    {
        $response = $this
            ->withHeaders(
                $this->apiHeaders(false)
            )
            ->getJson(
                '/api/products?per_page=5'
            );

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->assertNull(
            $response->headers->get(
                'X-Simulated-Failure-Triggered'
            )
        );
    }

    public function test_failure_scenario_requires_dedicated_key(): void
    {
        $this
            ->withHeaders(
                $this->apiHeaders(false)
            )
            ->getJson(
                '/api/products'
                . '?failure_scenario=service_unavailable'
            )
            ->assertStatus(403)
            ->assertJsonPath(
                'error_code',
                'INVALID_FAILURE_SIMULATION_KEY'
            );

        $this
            ->withHeaders([
                'X-ERP-Token' =>
                    self::API_TOKEN,

                'X-ERP-Failure-Key' =>
                    'incorrect-key',

                'Accept' =>
                    'application/json',
            ])
            ->getJson(
                '/api/products'
                . '?failure_scenario=service_unavailable'
            )
            ->assertStatus(403);
    }

    public function test_service_unavailable_scenario(): void
    {
        $databaseCount = ErpProduct::query()->count();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=service_unavailable'
                . '&failure_probability=100'
                . '&failure_retry_after=45'
                . '&failure_seed=1001'
            );

        $response
            ->assertStatus(503)
            ->assertHeader(
                'X-Simulated-Failure-Scenario',
                'service_unavailable'
            )
            ->assertHeader(
                'X-Simulated-Failure-Triggered',
                'true'
            )
            ->assertHeader(
                'Retry-After',
                '45'
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
                'failure.retryable',
                true
            )
            ->assertJsonPath(
                'failure.database_modified',
                false
            );

        $this->assertSame(
            $databaseCount,
            ErpProduct::query()->count()
        );
    }

    public function test_gateway_timeout_scenario(): void
    {
        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=gateway_timeout'
                . '&failure_probability=100'
            )
            ->assertStatus(504)
            ->assertJsonPath(
                'error_code',
                'SIMULATED_GATEWAY_TIMEOUT'
            )
            ->assertJsonPath(
                'failure.retryable',
                true
            );
    }

    public function test_rate_limited_scenario(): void
    {
        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=rate_limited'
                . '&failure_probability=100'
                . '&failure_retry_after=60'
            )
            ->assertStatus(429)
            ->assertHeader(
                'Retry-After',
                '60'
            )
            ->assertJsonPath(
                'error_code',
                'SIMULATED_RATE_LIMIT_EXCEEDED'
            );
    }

    public function test_internal_error_scenario(): void
    {
        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=internal_error'
                . '&failure_probability=100'
            )
            ->assertStatus(500)
            ->assertJsonPath(
                'error_code',
                'SIMULATED_INTERNAL_ERROR'
            )
            ->assertJsonPath(
                'failure.retryable',
                false
            );
    }

    public function test_malformed_response_scenario(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=malformed_response'
                . '&failure_probability=100'
            );

        $response
            ->assertOk()
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

        $payload = $response->json();

        $this->assertArrayNotHasKey(
            'data',
            $payload
        );

        $this->assertArrayNotHasKey(
            'links',
            $payload
        );
    }

    public function test_slow_response_returns_valid_data(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?per_page=5'
                . '&failure_scenario=slow_response'
                . '&failure_probability=100'
                . '&failure_delay_ms=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
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
    }

    public function test_zero_probability_returns_normal_response(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?per_page=5'
                . '&failure_scenario=service_unavailable'
                . '&failure_probability=0'
            );

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertHeader(
                'X-Simulated-Failure-Triggered',
                'false'
            );
    }

    public function test_none_scenario_returns_normal_response(): void
    {
        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?per_page=5'
                . '&failure_scenario=none'
            )
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertHeader(
                'X-Simulated-Failure-Triggered',
                'false'
            );
    }

    public function test_invalid_failure_parameters_are_rejected(): void
    {
        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=invalid_failure'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'failure_scenario',
            ]);

        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=service_unavailable'
                . '&failure_probability=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'failure_probability',
            ]);

        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=slow_response'
                . '&failure_delay_ms=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'failure_delay_ms',
            ]);
    }

    public function test_disabled_simulation_is_rejected(): void
    {
        config([
            'erp_failure_simulation.enabled' =>
                false,
        ]);

        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/products'
                . '?failure_scenario=service_unavailable'
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error_code',
                'FAILURE_SIMULATION_DISABLED'
            );
    }
}