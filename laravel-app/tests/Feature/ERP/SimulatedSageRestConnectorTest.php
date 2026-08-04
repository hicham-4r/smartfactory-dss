<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\ErpSyncCursor;
use App\DTOs\ERP\SimulatedSageConnectorConfig;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpAuthenticationException;
use App\Exceptions\ERP\ErpConfigurationException;
use App\Exceptions\ERP\ErpInvalidResponseException;
use App\Exceptions\ERP\ErpTransportException;
use App\Services\ERP\SimulatedSageResponseNormalizer;
use App\Services\ERP\SimulatedSageRestConnector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimulatedSageRestConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set(
            'erp.default',
            'simulated_sage'
        );

        config()->set(
            'erp.logging.channel',
            'null'
        );

        config()->set(
            'erp.connectors.simulated_sage',
            $this->validSettings()
        );

        $this->forgetConnectorInstances();
    }

    public function test_service_container_resolves_capability_based_secure_connector(): void
    {
        $connector = app(
            ErpConnectorInterface::class
        );

        $this->assertInstanceOf(
            SimulatedSageRestConnector::class,
            $connector
        );

        $this->assertSame(
            'simulated_sage',
            $connector->sourceSystem()
        );

        foreach (
            ErpResource::cases()
            as $resource
        ) {
            if (
                $resource ===
                ErpResource::RunLogs
            ) {
                $this->assertFalse(
                    $connector->supports(
                        $resource
                    )
                );

                continue;
            }

            $this->assertTrue(
                $connector->supports(
                    $resource
                )
            );
        }
    }

    public function test_configuration_requires_https_base_url(): void
    {
        $settings =
            $this->validSettings();

        $settings['base_url'] =
            'http://sage-erp-simulator.test';

        $this->expectException(
            ErpConfigurationException::class
        );

        SimulatedSageConnectorConfig::fromArray(
            settings: $settings,
            allowInsecureTls: false
        );
    }

    public function test_configuration_requires_erp_token(): void
    {
        $settings =
            $this->validSettings();

        $settings['token'] = '';

        $this->expectException(
            ErpConfigurationException::class
        );

        SimulatedSageConnectorConfig::fromArray(
            settings: $settings,
            allowInsecureTls: false
        );
    }

    public function test_configuration_allows_local_only_resource_to_have_no_endpoint(): void
    {
        $configuration =
            SimulatedSageConnectorConfig::fromArray(
                settings:
                    $this->validSettings(),

                allowInsecureTls:
                    false
            );

        $this->assertArrayNotHasKey(
            ErpResource::RunLogs->value,
            $configuration->endpoints
        );

        $this->assertFalse(
            $configuration->supports(
                ErpResource::RunLogs
            )
        );

        $this->assertTrue(
            $configuration->supports(
                ErpResource::WorkOrders
            )
        );

        $this->assertSame(
            '/api/production-orders',
            $configuration->endpointFor(
                ErpResource::WorkOrders
            )
        );
    }

    public function test_requesting_endpoint_for_unsupported_local_resource_is_rejected(): void
    {
        $configuration =
            SimulatedSageConnectorConfig::fromArray(
                settings:
                    $this->validSettings(),

                allowInsecureTls:
                    false
            );

        $this->expectException(
            ErpConfigurationException::class
        );

        $configuration->endpointFor(
            ErpResource::RunLogs
        );
    }

    public function test_configuration_rejects_unknown_resource_endpoint(): void
    {
        $settings =
            $this->validSettings();

        $settings['endpoints'][
            'unknown_resource'
        ] = '/api/unknown';

        $this->expectException(
            ErpConfigurationException::class
        );

        SimulatedSageConnectorConfig::fromArray(
            settings: $settings,
            allowInsecureTls: false
        );
    }

    public function test_configuration_rejects_absolute_resource_endpoint(): void
    {
        $settings =
            $this->validSettings();

        $settings['endpoints'][
            'products'
        ] = 'https://untrusted.example.test/collect-token';

        $this->expectException(
            ErpConfigurationException::class
        );

        SimulatedSageConnectorConfig::fromArray(
            settings: $settings,
            allowInsecureTls: false
        );
    }

    public function test_fetch_page_sends_custom_erp_token_and_incremental_parameters(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/products*' =>
                Http::response(
                    [
                        'data' => [
                            [
                                'external_id' =>
                                    'PRODUCT-001',

                                'code' =>
                                    'VAL-ORA-1L',

                                'name' =>
                                    'Valencia Orange 1 L',

                                'product_family_external_id' =>
                                    'FAMILY-PREMIUM',

                                'quantity_unit' =>
                                    'bottles',

                                'is_active' =>
                                    true,

                                'source_version' =>
                                    7,

                                'updated_at' =>
                                    '2026-07-30T12:00:00+00:00',
                            ],
                        ],

                        'meta' => [
                            'current_page' =>
                                2,

                            'per_page' =>
                                50,

                            'total' =>
                                1,

                            'next_page' =>
                                null,

                            'next_cursor' =>
                                null,

                            'request_id' =>
                                'ERP-REQ-001',
                        ],
                    ],
                    200,
                    [
                        'Content-Type' =>
                            'application/json',

                        'X-Request-ID' =>
                            'ERP-REQ-001',
                    ]
                ),
        ]);

        $page = app(
            ErpConnectorInterface::class
        )->fetchPage(
            ErpResource::Products,

            new ErpPageRequest(
                page: 2,
                perPage: 50,

                cursor:
                    new ErpSyncCursor(
                        updatedSince:
                            CarbonImmutable::parse(
                                '2026-07-01 00:00:00'
                            ),

                        opaqueToken:
                            'cursor-page-2',

                        sourceVersion:
                            6
                    ),

                filters: [
                    'date_from' =>
                        '2026-07-01',

                    'date_to' =>
                        '2026-07-31',

                    'status' =>
                        'active',
                ]
            )
        );

        $this->assertCount(
            1,
            $page->records
        );

        $this->assertSame(
            'PRODUCT-001',
            $page
                ->records[0]
                ->identity
                ->externalId
        );

        $this->assertSame(
            7,
            $page
                ->records[0]
                ->sourceVersion
        );

        $this->assertSame(
            'ERP-REQ-001',
            $page->responseId
        );

        Http::assertSent(
            function (
                Request $request
            ): bool {
                $query = [];

                parse_str(
                    (string) parse_url(
                        $request->url(),
                        PHP_URL_QUERY
                    ),
                    $query
                );

                return str_starts_with(
                    $request->url(),
                    'https://sage-erp-simulator.test/api/products?'
                )
                    && $request->hasHeader(
                        'X-ERP-Token',
                        'test-token-0123456789abcdef'
                    )
                    && ! $request->hasHeader(
                        'Authorization'
                    )
                    && $request->hasHeader(
                        'Accept',
                        'application/json'
                    )
                    && $request->hasHeader(
                        'User-Agent',
                        'SmartFactory-DSS-Test/1.0'
                    )
                    && $request->hasHeader(
                        'X-ERP-Source',
                        'simulated_sage'
                    )
                    && isset(
                        $query['updated_since']
                    )
                    && (
                        $query['cursor']
                        ?? null
                    ) === 'cursor-page-2'
                    && (
                        $query['source_version']
                        ?? null
                    ) === '6'
                    && (
                        $query['page']
                        ?? null
                    ) === '2'
                    && (
                        $query['per_page']
                        ?? null
                    ) === '50'
                    && (
                        $query['date_from']
                        ?? null
                    ) === '2026-07-01'
                    && (
                        $query['date_to']
                        ?? null
                    ) === '2026-07-31';
            }
        );
    }

    public function test_production_resources_use_separate_simulator_endpoint_names(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/production-orders*' =>
                Http::response(
                    $this->emptyPaginator(
                        '/api/production-orders'
                    )
                ),

            'https://sage-erp-simulator.test/api/production-batches*' =>
                Http::response(
                    $this->emptyPaginator(
                        '/api/production-batches'
                    )
                ),

            'https://sage-erp-simulator.test/api/production-records*' =>
                Http::response(
                    $this->emptyPaginator(
                        '/api/production-records'
                    )
                ),
        ]);

        $connector = app(
            ErpConnectorInterface::class
        );

        $connector->fetchPage(
            ErpResource::WorkOrders,
            new ErpPageRequest(
                page: 1,
                perPage: 5
            )
        );

        $connector->fetchPage(
            ErpResource::Batches,
            new ErpPageRequest(
                page: 1,
                perPage: 5
            )
        );

        $connector->fetchPage(
            ErpResource::MachineRuns,
            new ErpPageRequest(
                page: 1,
                perPage: 5
            )
        );

        Http::assertSent(
            static fn (
                Request $request
            ): bool =>
                str_starts_with(
                    $request->url(),
                    'https://sage-erp-simulator.test/api/production-orders?'
                )
        );

        Http::assertSent(
            static fn (
                Request $request
            ): bool =>
                str_starts_with(
                    $request->url(),
                    'https://sage-erp-simulator.test/api/production-batches?'
                )
        );

        Http::assertSent(
            static fn (
                Request $request
            ): bool =>
                str_starts_with(
                    $request->url(),
                    'https://sage-erp-simulator.test/api/production-records?'
                )
        );

        Http::assertSentCount(3);
    }

    public function test_laravel_paginator_response_is_normalized(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/machines*' =>
                Http::response([
                    'current_page' =>
                        1,

                    'data' => [
                        [
                            'id' =>
                                101,

                            'code' =>
                                'FILL-01',

                            'name' =>
                                'Filler 01',

                            'production_line_external_id' =>
                                'LINE-01',

                            'updated_at' =>
                                '2026-07-30 12:00:00',
                        ],
                    ],

                    'first_page_url' =>
                        'https://sage-erp-simulator.test/api/machines?page=1',

                    'from' =>
                        1,

                    'last_page' =>
                        2,

                    'next_page_url' =>
                        'https://sage-erp-simulator.test/api/machines?page=2',

                    'path' =>
                        'https://sage-erp-simulator.test/api/machines',

                    'per_page' =>
                        1,

                    'prev_page_url' =>
                        null,

                    'to' =>
                        1,

                    'total' =>
                        2,
                ]),
        ]);

        $page = app(
            ErpConnectorInterface::class
        )->fetchPage(
            ErpResource::Machines,

            new ErpPageRequest(
                page: 1,
                perPage: 1
            )
        );

        $this->assertSame(
            1,
            $page->currentPage
        );

        $this->assertSame(
            2,
            $page->total
        );

        $this->assertSame(
            2,
            $page->nextPage
        );

        $this->assertTrue(
            $page->hasMore()
        );

        $this->assertSame(
            '101',
            $page
                ->records[0]
                ->identity
                ->externalId
        );
    }

    public function test_cursor_pagination_is_normalized(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/operators*' =>
                Http::response([
                    'data' => [
                        [
                            'id' =>
                                'OP-001',

                            'employee_number' =>
                                'EMP-001',

                            'name' =>
                                'Synthetic Operator',
                        ],
                    ],

                    'meta' => [
                        'current_page' =>
                            1,

                        'per_page' =>
                            100,

                        'next_cursor' =>
                            'cursor-next',
                    ],
                ]),
        ]);

        $request =
            new ErpPageRequest();

        $page = app(
            ErpConnectorInterface::class
        )->fetchPage(
            ErpResource::Operators,
            $request
        );

        $this->assertTrue(
            $page->hasMore()
        );

        $this->assertSame(
            'cursor-next',
            $page->nextCursor
        );

        $nextRequest =
            $page->nextRequest(
                $request
            );

        $this->assertNotNull(
            $nextRequest
        );

        $this->assertSame(
            'cursor-next',
            $nextRequest
                ->cursor
                ?->opaqueToken
        );
    }

    public function test_authentication_failure_is_not_retried(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/products*' =>
                Http::response(
                    [
                        'message' =>
                            'Unauthenticated.',
                    ],
                    401
                ),
        ]);

        try {
            app(
                ErpConnectorInterface::class
            )->fetchPage(
                ErpResource::Products,
                new ErpPageRequest()
            );

            $this->fail(
                'Authentication failure was not raised.'
            );
        } catch (
            ErpAuthenticationException $exception
        ) {
            $this->assertFalse(
                $exception->isRetryable()
            );
        }

        Http::assertSentCount(1);
    }

    public function test_rate_limit_is_retried_then_succeeds(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/products*' =>
                Http::sequence()
                    ->push(
                        [
                            'message' =>
                                'Too many requests.',
                        ],
                        429,
                        [
                            'Retry-After' =>
                                '1',
                        ]
                    )
                    ->push(
                        [
                            'data' =>
                                [],

                            'current_page' =>
                                1,

                            'per_page' =>
                                100,

                            'total' =>
                                0,
                        ]
                    ),
        ]);

        $page = app(
            ErpConnectorInterface::class
        )->fetchPage(
            ErpResource::Products,
            new ErpPageRequest()
        );

        $this->assertTrue(
            $page->isEmpty()
        );

        Http::assertSentCount(2);
    }

    public function test_server_failure_is_retried_and_converted_to_transport_exception(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/products*' =>
                Http::sequence()
                    ->pushStatus(503)
                    ->pushStatus(503)
                    ->pushStatus(503),
        ]);

        try {
            app(
                ErpConnectorInterface::class
            )->fetchPage(
                ErpResource::Products,
                new ErpPageRequest()
            );

            $this->fail(
                'The exhausted server failure was accepted.'
            );
        } catch (
            ErpTransportException $exception
        ) {
            $this->assertTrue(
                $exception->isRetryable()
            );

            $this->assertSame(
                ErpResource::Products,
                $exception->resource()
            );
        }

        Http::assertSentCount(3);
    }

    public function test_invalid_json_is_rejected(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/products*' =>
                Http::response(
                    '{invalid-json',
                    200,
                    [
                        'Content-Type' =>
                            'application/json',
                    ]
                ),
        ]);

        $this->expectException(
            ErpInvalidResponseException::class
        );

        app(
            ErpConnectorInterface::class
        )->fetchPage(
            ErpResource::Products,
            new ErpPageRequest()
        );
    }

    public function test_redirect_response_is_rejected(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/products*' =>
                Http::response(
                    '',
                    302,
                    [
                        'Location' =>
                            'https://untrusted.example.test/collect-token',
                    ]
                ),
        ]);

        $this->expectException(
            ErpInvalidResponseException::class
        );

        app(
            ErpConnectorInterface::class
        )->fetchPage(
            ErpResource::Products,
            new ErpPageRequest()
        );
    }

    public function test_health_endpoint_reports_healthy_connector(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/health' =>
                Http::response([
                    'status' =>
                        'ok',
                ]),
        ]);

        $health = app(
            ErpConnectorInterface::class
        )->health();

        $this->assertSame(
            ErpConnectorHealthStatus::Healthy,
            $health->status
        );

        $this->assertTrue(
            $health->isAvailable()
        );

        Http::assertSent(
            static fn (
                Request $request
            ): bool =>
                $request->hasHeader(
                    'X-ERP-Token',
                    'test-token-0123456789abcdef'
                )
                && ! $request->hasHeader(
                    'Authorization'
                )
        );
    }

    public function test_health_endpoint_reports_unavailable_after_retries(): void
    {
        Http::fake([
            'https://sage-erp-simulator.test/api/health' =>
                Http::sequence()
                    ->pushStatus(503)
                    ->pushStatus(503)
                    ->pushStatus(503),
        ]);

        $health = app(
            ErpConnectorInterface::class
        )->health();

        $this->assertSame(
            ErpConnectorHealthStatus::Unavailable,
            $health->status
        );

        $this->assertFalse(
            $health->isAvailable()
        );

        Http::assertSentCount(3);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPaginator(
        string $path
    ): array {
        return [
            'current_page' =>
                1,

            'data' =>
                [],

            'from' =>
                null,

            'last_page' =>
                1,

            'next_page_url' =>
                null,

            'path' =>
                'https://sage-erp-simulator.test'
                .$path,

            'per_page' =>
                5,

            'prev_page_url' =>
                null,

            'to' =>
                null,

            'total' =>
                0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validSettings(): array
    {
        return [
            'source_system' =>
                'simulated_sage',

            'base_url' =>
                'https://sage-erp-simulator.test',

            'token' =>
                'test-token-0123456789abcdef',

            'verify_tls' =>
                true,

            'health_endpoint' =>
                '/api/health',

            'connect_timeout_seconds' =>
                2,

            'timeout_seconds' =>
                5,

            'retry_attempts' =>
                3,

            /*
             * Avoid sleeping during mocked tests.
             */
            'retry_delay_milliseconds' =>
                0,

            'retry_maximum_delay_milliseconds' =>
                0,

            'page_size' =>
                100,

            'maximum_page_size' =>
                100,

            'maximum_response_bytes' =>
                1048576,

            'user_agent' =>
                'SmartFactory-DSS-Test/1.0',

            'log_channel' =>
                'null',

            'endpoints' =>
                $this->endpointMap(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function endpointMap(): array
    {
        return [
            'product_families' =>
                '/api/product-families',

            'products' =>
                '/api/products',

            'production_lines' =>
                '/api/production-lines',

            'machines' =>
                '/api/machines',

            'shifts' =>
                '/api/shifts',

            'operators' =>
                '/api/operators',

            'operator_assignments' =>
                '/api/operator-assignments',

            'work_orders' =>
                '/api/production-orders',

            'batches' =>
                '/api/production-batches',

            'machine_runs' =>
                '/api/production-records',

            /*
             * No run_logs endpoint exists in the external Sage
             * simulator. Run logs remain local DSS telemetry.
             */

            'downtime_events' =>
                '/api/downtime-events',

            'machine_status_events' =>
                '/api/machine-status-events',

            'maintenance_history' =>
                '/api/maintenance-history',

            'inspections' =>
                '/api/inspections',

            'nonconformities' =>
                '/api/nonconformities',

            'finished_lots' =>
                '/api/finished-lots',
        ];
    }

    private function forgetConnectorInstances(): void
    {
        $this->app->forgetInstance(
            ErpConnectorInterface::class
        );

        $this->app->forgetInstance(
            SimulatedSageConnectorConfig::class
        );

        $this->app->forgetInstance(
            SimulatedSageResponseNormalizer::class
        );

        $this->app->forgetInstance(
            SimulatedSageRestConnector::class
        );
    }
}