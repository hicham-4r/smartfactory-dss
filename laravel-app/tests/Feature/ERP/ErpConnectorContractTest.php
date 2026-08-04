<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\ErpSyncCursor;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpConfigurationException;
use App\Exceptions\ERP\ErpRateLimitException;
use App\Exceptions\ERP\ErpTransportException;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\Fakes\FakeErpConnector;
use Tests\TestCase;

class ErpConnectorContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'erp.default',
            'disabled'
        );

        $this->app->forgetInstance(
            ErpConnectorInterface::class
        );
    }

    public function test_service_container_resolves_disabled_connector_safely(): void
    {
        $connector = app(
            ErpConnectorInterface::class
        );

        $this->assertSame(
            'disabled',
            $connector->sourceSystem()
        );

        $this->assertSame(
            ErpConnectorHealthStatus::Disabled,
            $connector->health()->status
        );

        $this->assertFalse(
            $connector->supports(
                ErpResource::Products
            )
        );
    }

    public function test_disabled_connector_fails_closed_when_fetch_is_attempted(): void
    {
        $connector = app(
            ErpConnectorInterface::class
        );

        $this->expectException(
            ErpConfigurationException::class
        );

        $connector->fetchPage(
            ErpResource::Products,
            new ErpPageRequest()
        );
    }

    public function test_unknown_connector_driver_is_rejected(): void
    {
        config()->set(
            'erp.default',
            'unknown-driver'
        );

        $this->app->forgetInstance(
            ErpConnectorInterface::class
        );

        $this->expectException(
            ErpConfigurationException::class
        );

        app(ErpConnectorInterface::class);
    }

    public function test_source_identity_is_stable_and_validated(): void
    {
        $identity = new ErpSourceIdentity(
            sourceSystem: 'simulated_sage',
            resource: ErpResource::Products,
            externalId: 'PRODUCT-001'
        );

        $this->assertSame(
            'simulated_sage|products|PRODUCT-001',
            $identity->key()
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        new ErpSourceIdentity(
            sourceSystem: '',
            resource: ErpResource::Products,
            externalId: 'PRODUCT-002'
        );
    }

    public function test_record_checksum_is_deterministic_for_associative_key_order(): void
    {
        $identity = new ErpSourceIdentity(
            sourceSystem: 'fake_erp',
            resource: ErpResource::Products,
            externalId: 'P-001'
        );

        $first = new ErpSourceRecord(
            identity: $identity,

            attributes: [
                'name' => 'Orange Juice',

                'details' => [
                    'volume' => 1,
                    'unit' => 'L',
                ],
            ]
        );

        $second = new ErpSourceRecord(
            identity: $identity,

            attributes: [
                'details' => [
                    'unit' => 'L',
                    'volume' => 1,
                ],

                'name' => 'Orange Juice',
            ]
        );

        $this->assertSame(
            $first->checksum,
            $second->checksum
        );

        $this->assertSame(
            64,
            strlen($first->checksum)
        );
    }

    public function test_page_request_normalizes_incremental_parameters_and_filters(): void
    {
        $request = new ErpPageRequest(
            page: 2,
            perPage: 50,

            cursor: new ErpSyncCursor(
                updatedSince:
                    CarbonImmutable::parse(
                        '2026-07-15 10:00:00'
                    ),

                opaqueToken: 'cursor-2',
                sourceVersion: 8,
            ),

            filters: [
                'status' => 'active',
            ]
        );

        $parameters =
            $request->toQueryParameters();

        $this->assertSame(
            2,
            $parameters['page']
        );

        $this->assertSame(
            50,
            $parameters['per_page']
        );

        $this->assertSame(
            'active',
            $parameters['status']
        );

        $this->assertSame(
            'cursor-2',
            $parameters['cursor']
        );

        $this->assertSame(
            8,
            $parameters['source_version']
        );

        $this->assertArrayHasKey(
            'updated_since',
            $parameters
        );
    }

    public function test_page_rejects_records_from_another_resource(): void
    {
        $record = new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem: 'fake_erp',
                    resource:
                        ErpResource::Machines,
                    externalId: 'M-001'
                ),

            attributes: [
                'name' => 'Filler',
            ]
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        new ErpPage(
            resource: ErpResource::Products,
            records: [$record],
            currentPage: 1,
            perPage: 100
        );
    }

    public function test_fake_connector_paginates_and_preserves_next_request_context(): void
    {
        $connector = new FakeErpConnector();

        $connector->seed(
            ErpResource::Products,
            [
                $this->productRecord(
                    'P-001',
                    'Orange',
                    '2026-07-15 09:00:00'
                ),

                $this->productRecord(
                    'P-002',
                    'Pineapple',
                    '2026-07-15 10:00:00'
                ),

                $this->productRecord(
                    'P-003',
                    'Apple',
                    '2026-07-15 11:00:00'
                ),
            ]
        );

        $request = new ErpPageRequest(
            page: 1,
            perPage: 2,

            filters: [
                'is_active' => true,
            ]
        );

        $firstPage = $connector->fetchPage(
            ErpResource::Products,
            $request
        );

        $this->assertCount(
            2,
            $firstPage->records
        );

        $this->assertSame(
            3,
            $firstPage->total
        );

        $this->assertTrue(
            $firstPage->hasMore()
        );

        $nextRequest =
            $firstPage->nextRequest(
                $request
            );

        $this->assertNotNull(
            $nextRequest
        );

        $this->assertSame(
            2,
            $nextRequest->page
        );

        $this->assertSame(
            [
                'is_active' => true,
            ],
            $nextRequest->filters
        );

        $secondPage =
            $connector->fetchPage(
                ErpResource::Products,
                $nextRequest
            );

        $this->assertCount(
            1,
            $secondPage->records
        );

        $this->assertFalse(
            $secondPage->hasMore()
        );

        $this->assertCount(
            2,
            $connector->requests()
        );
    }

    public function test_incremental_cursor_excludes_older_records(): void
    {
        $connector = new FakeErpConnector();

        $connector->seed(
            ErpResource::Products,
            [
                $this->productRecord(
                    'P-OLD',
                    'Old product',
                    '2026-07-15 09:00:00'
                ),

                $this->productRecord(
                    'P-NEW',
                    'New product',
                    '2026-07-15 11:00:00'
                ),
            ]
        );

        $page = $connector->fetchPage(
            ErpResource::Products,

            new ErpPageRequest(
                cursor:
                    new ErpSyncCursor(
                        updatedSince:
                            CarbonImmutable::parse(
                                '2026-07-15 10:00:00'
                            )
                    )
            )
        );

        $this->assertCount(
            1,
            $page->records
        );

        $this->assertSame(
            'P-NEW',
            $page->records[0]
                ->identity
                ->externalId
        );
    }

    public function test_connector_exceptions_expose_retryability_and_redact_secrets(): void
    {
        $transport =
            ErpTransportException
                ::unreachable(
                    resource:
                        ErpResource::Products,

                    safeContext: [
                        'endpoint' =>
                            '/api/products',

                        'token' =>
                            'do-not-log',
                    ]
                );

        $this->assertTrue(
            $transport->isRetryable()
        );

        $this->assertSame(
            '[REDACTED]',
            $transport->context()['token']
        );

        $rateLimit =
            ErpRateLimitException
                ::forResource(
                    resource:
                        ErpResource::Products,

                    retryAfterSeconds: 30
                );

        $this->assertTrue(
            $rateLimit->isRetryable()
        );

        $this->assertSame(
            30,
            $rateLimit
                ->retryAfterSeconds()
        );
    }

    private function productRecord(
        string $externalId,
        string $name,
        string $updatedAt
    ): ErpSourceRecord {
        return new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem: 'fake_erp',

                    resource:
                        ErpResource::Products,

                    externalId:
                        $externalId
                ),

            attributes: [
                'name' => $name,
                'is_active' => true,
            ],

            sourceVersion: 1,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    $updatedAt
                )
        );
    }
}