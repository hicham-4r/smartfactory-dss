<?php

namespace Tests\Feature\ERP;

use App\Enums\ERP\ErpResource;
use App\Models\Product;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ErpSimulatorApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN =
        'test-simulator-token-0123456789abcdef0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'erp.simulator.token',
            self::TOKEN
        );

        config()->set(
            'erp.simulator.enforce_https',
            true
        );

        config()->set(
            'erp.simulator.maximum_page_size',
            50
        );

        config()->set(
            'erp.simulator.cursor_ttl_seconds',
            3600
        );

        config()->set(
            'erp.simulator.rate_limit_per_minute',
            120
        );

        config()->set(
            'erp.logging.channel',
            'null'
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_missing_token_is_rejected(): void
    {
        $this
            ->withHeader(
                'Accept',
                'application/json'
            )
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.health'
                )
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error',
                'unauthenticated'
            )
            ->assertHeader(
                'WWW-Authenticate'
            );
    }

    public function test_incorrect_token_is_rejected(): void
    {
        $this
            ->withToken(
                'incorrect-token-value-that-is-long-enough'
            )
            ->withHeader(
                'Accept',
                'application/json'
            )
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.health'
                )
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error',
                'unauthenticated'
            );
    }

    public function test_insecure_request_is_rejected(): void
    {
        $this
            ->authenticatedRequest()
            ->getJson(
                $this->insecureRoute(
                    'erp.simulator.health'
                )
            )
            ->assertStatus(426)
            ->assertJsonPath(
                'error',
                'https_required'
            );
    }

    public function test_authenticated_health_endpoint_is_available(): void
    {
        $response = $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.health'
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'ok'
            )
            ->assertJsonPath(
                'service',
                'SmartFactory Simulated Sage ERP'
            )
            ->assertJsonPath(
                'source_system',
                'simulated_sage'
            )
            ->assertJsonPath(
                'version',
                'v1'
            )
            ->assertJsonPath(
                'resources.total',
                count(
                    ErpResource::cases()
                )
            )
            ->assertJsonPath(
                'resources.available',
                count(
                    ErpResource::cases()
                )
            )
            ->assertJsonPath(
                'resources.missing',
                []
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->assertNotEmpty(
            $response->json(
                'request_id'
            )
        );

        $this->assertNotEmpty(
            $response->headers->get(
                'X-Request-ID'
            )
        );
    }

    public function test_products_endpoint_returns_normalized_page(): void
    {
        $response = $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.resources.products',
                    [
                        'page' => 1,
                        'per_page' => 5,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.resource',
                ErpResource::Products->value
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                5
            )
            ->assertJsonCount(
                5,
                'data'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'source_version',
                        'source_updated_at',
                    ],
                ],

                'meta' => [
                    'resource',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'next_page',
                    'next_cursor',
                    'request_id',
                ],

                'links' => [
                    'next_cursor',
                ],
            ])
            ->assertHeader(
                'X-ERP-Resource',
                ErpResource::Products->value
            )
            ->assertHeader(
                'X-ERP-Source',
                'simulated_sage'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            );

        $firstRecord = $response->json(
            'data.0'
        );

        $this->assertIsArray(
            $firstRecord
        );

        $this->assertNotEmpty(
            $firstRecord['external_id']
                ?? null
        );

        $this->assertIsInt(
            $firstRecord['source_version']
                ?? null
        );

        $this->assertNotEmpty(
            $firstRecord['source_updated_at']
                ?? null
        );

        foreach (
            array_keys($firstRecord)
            as $field
        ) {
            $this->assertDoesNotMatchRegularExpression(
                '/password|token|secret|authorization|remember|two_factor|recovery|api[_-]?key/i',
                $field
            );
        }
    }

    public function test_signed_cursor_loads_next_page(): void
    {
        $firstPage = $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.resources.products',
                    [
                        'page' => 1,
                        'per_page' => 2,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonCount(
                2,
                'data'
            );

        $cursor = $firstPage->json(
            'meta.next_cursor'
        );

        $this->assertIsString(
            $cursor
        );

        $this->assertNotSame(
            '',
            $cursor
        );

        $firstPageExternalIds = collect(
            $firstPage->json('data')
        )
            ->pluck('external_id')
            ->all();

        $secondPage = $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.resources.products',
                    [
                        'page' => 2,
                        'per_page' => 2,
                        'cursor' => $cursor,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonCount(
                2,
                'data'
            );

        $secondPageExternalIds = collect(
            $secondPage->json('data')
        )
            ->pluck('external_id')
            ->all();

        $this->assertEmpty(
            array_intersect(
                $firstPageExternalIds,
                $secondPageExternalIds
            )
        );
    }

    public function test_incremental_updated_since_filter_is_applied(): void
    {
        $products = Product::query()
            ->orderBy('id')
            ->limit(2)
            ->get();

        $this->assertCount(
            2,
            $products
        );

        $oldProduct = $products->get(0);
        $newProduct = $products->get(1);

        $this->assertNotNull(
            $oldProduct
        );

        $this->assertNotNull(
            $newProduct
        );

        /*
         * The ERP registry prioritizes source_updated_at when that
         * column exists. Otherwise, it uses updated_at.
         *
         * Update both columns when possible so this test works with
         * both the original master-data schema and the extended
         * synchronization-ready schema.
         */
        $oldTimestampValues = [
            'updated_at' =>
                '2026-07-01 08:00:00',
        ];

        $newTimestampValues = [
            'updated_at' =>
                '2026-07-30 08:00:00',
        ];

        if (
            Schema::hasColumn(
                'products',
                'source_updated_at'
            )
        ) {
            $oldTimestampValues[
                'source_updated_at'
            ] = '2026-07-01 08:00:00';

            $newTimestampValues[
                'source_updated_at'
            ] = '2026-07-30 08:00:00';
        }

        /*
         * Move every seeded product before the synchronization
         * boundary.
         */
        DB::table('products')
            ->update(
                $oldTimestampValues
            );

        /*
         * Move only the selected product after the boundary.
         */
        DB::table('products')
            ->where(
                'id',
                $newProduct->getKey()
            )
            ->update(
                $newTimestampValues
            );

        $response = $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.resources.products',
                    [
                        'updated_since' =>
                            '2026-07-15T00:00:00+00:00',

                        'per_page' => 50,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.resource',
                ErpResource::Products->value
            );

        /*
         * This test verifies timestamp filtering. Compare the
         * returned product code instead of external_id because
         * external_id and code are separate ERP identity fields.
         */
        $returnedCodes = collect(
            $response->json('data')
        )
            ->pluck('code')
            ->filter(
                static fn (mixed $value): bool =>
                    is_string($value)
                    || is_int($value)
            )
            ->map(
                static fn (mixed $value): string =>
                    (string) $value
            )
            ->values()
            ->all();

        $this->assertNotContains(
            (string) $oldProduct->code,
            $returnedCodes
        );

        $this->assertContains(
            (string) $newProduct->code,
            $returnedCodes
        );

        $this->assertCount(
            1,
            $returnedCodes
        );
    }

    public function test_page_size_above_limit_is_rejected(): void
    {
        $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.resources.products',
                    [
                        'per_page' => 51,
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'per_page'
            );
    }

    public function test_tampered_cursor_is_rejected(): void
    {
        $this
            ->authenticatedRequest()
            ->getJson(
                $this->secureRoute(
                    'erp.simulator.resources.products',
                    [
                        'page' => 2,
                        'per_page' => 2,

                        'cursor' =>
                            'tampered.cursor-value',
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'cursor'
            );
    }

    public function test_all_erp_resource_routes_are_registered(): void
    {
        $this->assertTrue(
            Route::has(
                'erp.simulator.health'
            )
        );

        foreach (
            ErpResource::cases()
            as $resource
        ) {
            $routeName =
                'erp.simulator.resources.'
                .$resource->value;

            $this->assertTrue(
                Route::has($routeName),

                'Missing route for ERP resource: '
                .$resource->value
            );
        }
    }

    private function authenticatedRequest(): static
    {
        return $this
            ->withToken(
                self::TOKEN
            )
            ->withHeader(
                'Accept',
                'application/json'
            );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function secureRoute(
        string $name,
        array $parameters = []
    ): string {
        return 'https://localhost'
            .$this->relativeRoute(
                $name,
                $parameters
            );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function insecureRoute(
        string $name,
        array $parameters = []
    ): string {
        return 'http://localhost'
            .$this->relativeRoute(
                $name,
                $parameters
            );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function relativeRoute(
        string $name,
        array $parameters = []
    ): string {
        $path = route(
            $name,
            $parameters,
            false
        );

        return '/'.ltrim(
            $path,
            '/'
        );
    }
}