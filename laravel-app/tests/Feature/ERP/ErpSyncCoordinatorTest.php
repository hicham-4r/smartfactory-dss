<?php

namespace Tests\Feature\ERP;

use App\Contracts\ERP\ErpConnectorInterface;
use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpConnectorHealth;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Exceptions\ERP\ErpTransportException;
use App\Models\ErpSyncState;
use App\Services\ERP\Sync\ErpMappedEntityPersister;
use App\Services\ERP\Sync\ErpSyncCoordinator;
use App\Services\ERP\Sync\ErpSyncRunTracker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class ErpSyncCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_imports_dependencies_and_multiple_pages(): void
    {
        $connector = new FakePagedErpConnector(
            [
                'product_families' => [
                    1 => $this->page(
                        resource:
                            ErpResource::ProductFamilies,

                        records: [
                            $this->productFamilyRecord(),
                        ]
                    ),
                ],

                'products' => [
                    1 => $this->page(
                        resource:
                            ErpResource::Products,

                        records: [
                            $this->productRecord(
                                externalId:
                                    'SYNC-PRODUCT-001',

                                code:
                                    'SYNC-JUICE-001'
                            ),
                        ],

                        currentPage: 1,
                        nextPage: 2,

                        nextCursor:
                            'products-page-2'
                    ),

                    2 => $this->page(
                        resource:
                            ErpResource::Products,

                        records: [
                            $this->productRecord(
                                externalId:
                                    'SYNC-PRODUCT-002',

                                code:
                                    'SYNC-JUICE-002'
                            ),
                        ],

                        currentPage: 2
                    ),
                ],
            ]
        );

        $run = $this
            ->coordinator($connector)
            ->synchronize(
                resources: [
                    ErpResource::ProductFamilies,
                    ErpResource::Products,
                ],

                perPage: 1,
                maximumPagesPerResource: 10
            );

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $run->status
        );

        $this->assertSame(
            3,
            $run->pages_processed
        );

        $this->assertSame(
            3,
            $run->records_fetched
        );

        $this->assertSame(
            3,
            $run->records_created
        );

        $familyId = DB::table(
            'product_families'
        )
            ->where(
                'code',
                'SYNC-FAMILY'
            )
            ->value('id');

        $this->assertNotNull(
            $familyId
        );

        $this->assertDatabaseHas(
            'products',
            [
                'code' =>
                    'SYNC-JUICE-001',

                'product_family_id' =>
                    $familyId,
            ]
        );

        $this->assertDatabaseHas(
            'products',
            [
                'code' =>
                    'SYNC-JUICE-002',

                'product_family_id' =>
                    $familyId,
            ]
        );
    }

    public function test_second_identical_sync_skips_unchanged_records(): void
    {
        $firstConnector =
            new FakePagedErpConnector(
                $this->singleProductPages()
            );

        $firstRun = $this
            ->coordinator($firstConnector)
            ->synchronize(
                resources: [
                    ErpResource::ProductFamilies,
                    ErpResource::Products,
                ]
            );

        $this->assertSame(
            2,
            $firstRun->records_created
        );

        $secondConnector =
            new FakePagedErpConnector(
                $this->singleProductPages()
            );

        $secondRun = $this
            ->coordinator($secondConnector)
            ->synchronize(
                resources: [
                    ErpResource::ProductFamilies,
                    ErpResource::Products,
                ],

                fromStart: true
            );

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $secondRun->status
        );

        $this->assertSame(
            0,
            $secondRun->records_created
        );

        $this->assertSame(
            0,
            $secondRun->records_updated
        );

        $this->assertSame(
            2,
            $secondRun->records_skipped
        );

        $this->assertSame(
            1,
            DB::table('products')
                ->where(
                    'code',
                    'SYNC-JUICE-001'
                )
                ->count()
        );
    }

    public function test_newer_source_version_updates_existing_record(): void
    {
        $initialConnector =
            new FakePagedErpConnector(
                $this->singleProductPages(
                    productVersion: 1,
                    productName:
                        'Initial synchronized product'
                )
            );

        $this->coordinator(
            $initialConnector
        )->synchronize(
            resources: [
                ErpResource::ProductFamilies,
                ErpResource::Products,
            ]
        );

        $updatedConnector =
            new FakePagedErpConnector(
                $this->singleProductPages(
                    productVersion: 2,
                    productName:
                        'Updated synchronized product'
                )
            );

        $updatedRun = $this
            ->coordinator(
                $updatedConnector
            )
            ->synchronize(
                resources: [
                    ErpResource::ProductFamilies,
                    ErpResource::Products,
                ],

                fromStart: true
            );

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $updatedRun->status
        );

        $this->assertSame(
            1,
            $updatedRun->records_updated
        );

        $this->assertSame(
            1,
            $updatedRun->records_skipped
        );

        $this->assertDatabaseHas(
            'products',
            [
                'code' =>
                    'SYNC-JUICE-001',

                'name' =>
                    'Updated synchronized product',
            ]
        );
    }

    public function test_failed_page_resumes_from_saved_checkpoint(): void
    {
        /*
         * Create the family required by both product pages.
         */
        $familyConnector =
            new FakePagedErpConnector([
                'product_families' => [
                    1 => $this->page(
                        resource:
                            ErpResource::ProductFamilies,

                        records: [
                            $this->productFamilyRecord(),
                        ]
                    ),
                ],
            ]);

        $this->coordinator(
            $familyConnector
        )->synchronize([
            ErpResource::ProductFamilies,
        ]);

        $failingConnector =
            new FakePagedErpConnector([
                'products' => [
                    1 => $this->page(
                        resource:
                            ErpResource::Products,

                        records: [
                            $this->productRecord(
                                externalId:
                                    'RESUME-PRODUCT-001',

                                code:
                                    'RESUME-JUICE-001'
                            ),
                        ],

                        currentPage: 1,
                        nextPage: 2,

                        nextCursor:
                            'resume-products-page-2'
                    ),

                    2 =>
                        ErpTransportException
                            ::unreachable(
                                resource:
                                    ErpResource::Products,

                                safeContext: [
                                    'status_code' => 503,
                                ]
                            ),
                ],
            ]);

        $failedRun = $this
            ->coordinator(
                $failingConnector
            )
            ->synchronize([
                ErpResource::Products,
            ]);

        $this->assertSame(
            ErpSyncRunStatus::Failed,
            $failedRun->status
        );

        $state = ErpSyncState::query()
            ->where(
                'source_system',
                'simulated_sage'
            )
            ->where(
                'resource',
                ErpResource::Products->value
            )
            ->firstOrFail();

        $this->assertSame(
            2,
            $state->resume_page
        );

        $this->assertSame(
            'resume-products-page-2',
            $state->resume_cursor
        );

        $resumeConnector =
            new FakePagedErpConnector([
                'products' => [
                    2 => $this->page(
                        resource:
                            ErpResource::Products,

                        records: [
                            $this->productRecord(
                                externalId:
                                    'RESUME-PRODUCT-002',

                                code:
                                    'RESUME-JUICE-002'
                            ),
                        ],

                        currentPage: 2
                    ),
                ],
            ]);

        $resumedRun = $this
            ->coordinator(
                $resumeConnector
            )
            ->synchronize([
                ErpResource::Products,
            ]);

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $resumedRun->status
        );

        $this->assertNotEmpty(
            $resumeConnector->requests
        );

        $firstResumeRequest =
            $resumeConnector->requests[0];

        $this->assertSame(
            2,
            $firstResumeRequest->page
        );

        $this->assertSame(
            'resume-products-page-2',
            $firstResumeRequest
                ->cursor
                ?->opaqueToken
        );

        $state->refresh();

        $this->assertSame(
            1,
            $state->resume_page
        );

        $this->assertNull(
            $state->resume_cursor
        );

        $this->assertDatabaseHas(
            'products',
            [
                'code' =>
                    'RESUME-JUICE-001',
            ]
        );

        $this->assertDatabaseHas(
            'products',
            [
                'code' =>
                    'RESUME-JUICE-002',
            ]
        );
    }

    public function test_simulated_sage_numeric_dependency_id_is_resolved_safely(): void
    {
        /*
         * Synchronize the dependency normally first.
         */
        $familyConnector =
            new FakePagedErpConnector([
                'product_families' => [
                    1 => $this->page(
                        resource:
                            ErpResource::ProductFamilies,

                        records: [
                            $this->productFamilyRecord(),
                        ]
                    ),
                ],
            ]);

        $familyRun = $this
            ->coordinator($familyConnector)
            ->synchronize(
                resources: [
                    ErpResource::ProductFamilies,
                ],

                fromStart: true
            );

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $familyRun->status
        );

        $familyId = DB::table(
            'product_families'
        )
            ->where(
                'code',
                'SYNC-FAMILY'
            )
            ->value('id');

        $this->assertNotNull(
            $familyId
        );

        /*
         * Reproduce the live simulator payload. The relationship
         * contains the local numeric identifier rather than the
         * product-family ERP external identifier.
         */
        $productRecord =
            $this->sourceRecord(
                resource:
                    ErpResource::Products,

                externalId:
                    'NUMERIC-DEPENDENCY-PRODUCT-001',

                attributes: [
                    'code' =>
                        'NUMERIC-DEPENDENCY-JUICE-001',

                    'sku' =>
                        'SKU-NUMERIC-DEPENDENCY-001',

                    'name' =>
                        'Numeric dependency test product',

                    /*
                     * The mapper supports the documented simulator
                     * numeric relationship alias.
                     */
                    'product_family_id' =>
                        $familyId,

                    'quantity_unit' =>
                        'bottles',

                    'is_active' =>
                        true,
                ],

                version: 1
            );

        $productConnector =
            new FakePagedErpConnector([
                'products' => [
                    1 => $this->page(
                        resource:
                            ErpResource::Products,

                        records: [
                            $productRecord,
                        ]
                    ),
                ],
            ]);

        $productRun = $this
            ->coordinator($productConnector)
            ->synchronize(
                resources: [
                    ErpResource::Products,
                ],

                fromStart: true
            );

        $this->assertSame(
            ErpSyncRunStatus::Completed,
            $productRun->status
        );

        $this->assertSame(
            0,
            $productRun->records_failed
        );

        $this->assertDatabaseHas(
            'products',
            [
                'code' =>
                    'NUMERIC-DEPENDENCY-JUICE-001',

                'product_family_id' =>
                    $familyId,
            ]
        );
    }

    private function coordinator(
        ErpConnectorInterface $connector
    ): ErpSyncCoordinator {
        return new ErpSyncCoordinator(
            connector: $connector,

            mapper:
                app(
                    ErpRecordMapperInterface::class
                ),

            persister:
                app(
                    ErpMappedEntityPersister::class
                ),

            tracker:
                app(
                    ErpSyncRunTracker::class
                )
        );
    }

    /**
     * @return array<string, array<int, ErpPage|Throwable>>
     */
    private function singleProductPages(
        int $productVersion = 1,
        string $productName =
            'Initial synchronized product'
    ): array {
        return [
            'product_families' => [
                1 => $this->page(
                    resource:
                        ErpResource::ProductFamilies,

                    records: [
                        $this->productFamilyRecord(),
                    ]
                ),
            ],

            'products' => [
                1 => $this->page(
                    resource:
                        ErpResource::Products,

                    records: [
                        $this->productRecord(
                            externalId:
                                'SYNC-PRODUCT-001',

                            code:
                                'SYNC-JUICE-001',

                            version:
                                $productVersion,

                            name:
                                $productName
                        ),
                    ]
                ),
            ],
        ];
    }

    private function productFamilyRecord(): ErpSourceRecord
    {
        return $this->sourceRecord(
            resource:
                ErpResource::ProductFamilies,

            externalId:
                'SYNC-FAMILY-001',

            attributes: [
                'code' =>
                    'SYNC-FAMILY',

                'name' =>
                    'Synchronized Product Family',

                'description' =>
                    'ERP synchronization test family.',

                'is_active' => true,
            ],

            version: 1
        );
    }

    private function productRecord(
        string $externalId,
        string $code,
        int $version = 1,
        string $name =
            'Synchronized product'
    ): ErpSourceRecord {
        return $this->sourceRecord(
            resource:
                ErpResource::Products,

            externalId:
                $externalId,

            attributes: [
                'code' => $code,
                'name' => $name,

                'product_family_external_id' =>
                    'SYNC-FAMILY-001',

                'sku' =>
                    'SKU-'.$code,

                'quantity_unit' =>
                    'bottles',

                'is_active' =>
                    true,
            ],

            version: $version
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function sourceRecord(
        ErpResource $resource,
        string $externalId,
        array $attributes,
        int $version
    ): ErpSourceRecord {
        return new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem:
                        'simulated_sage',

                    resource:
                        $resource,

                    externalId:
                        $externalId
                ),

            attributes: $attributes,

            sourceVersion:
                $version,

            sourceUpdatedAt:
                CarbonImmutable::parse(
                    '2026-07-31 12:00:00'
                ),

            receivedAt:
                CarbonImmutable::parse(
                    '2026-07-31 12:00:05'
                )
        );
    }

    /**
     * @param list<ErpSourceRecord> $records
     */
    private function page(
        ErpResource $resource,
        array $records,
        int $currentPage = 1,
        ?int $nextPage = null,
        ?string $nextCursor = null
    ): ErpPage {
        return new ErpPage(
            resource: $resource,
            records: $records,
            currentPage: $currentPage,

            perPage: max(
                1,
                count($records)
            ),

            total: null,
            nextPage: $nextPage,
            nextCursor: $nextCursor,

            fetchedAt:
                CarbonImmutable::now(),

            responseId:
                'FAKE-ERP-REQUEST-'
                .$resource->value
                .'-'
                .$currentPage
        );
    }
}

/**
 * Deterministic in-memory connector used only by these tests.
 */
final class FakePagedErpConnector implements ErpConnectorInterface
{
    /**
     * @var list<ErpPageRequest>
     */
    public array $requests = [];

    /**
     * @param array<string, array<int, ErpPage|Throwable>> $pages
     */
    public function __construct(
        private readonly array $pages
    ) {
    }

    public function name(): string
    {
        return 'Fake paged ERP connector';
    }

    public function sourceSystem(): string
    {
        return 'simulated_sage';
    }

    public function supports(
        ErpResource $resource
    ): bool {
        return array_key_exists(
            $resource->value,
            $this->pages
        );
    }

    public function health(): ErpConnectorHealth
    {
        return new ErpConnectorHealth(
            status:
                ErpConnectorHealthStatus::Healthy,

            checkedAt:
                CarbonImmutable::now(),

            latencyMilliseconds: 0,

            message:
                'Fake connector is healthy.'
        );
    }

    public function fetchPage(
        ErpResource $resource,
        ErpPageRequest $request
    ): ErpPage {
        $this->requests[] = $request;

        $entry =
            $this->pages[
                $resource->value
            ][$request->page]
            ?? null;

        if ($entry instanceof Throwable) {
            throw $entry;
        }

        if ($entry instanceof ErpPage) {
            return $entry;
        }

        return new ErpPage(
            resource: $resource,
            records: [],
            currentPage: $request->page,
            perPage: $request->perPage,
            total: 0,
            nextPage: null,
            nextCursor: null,

            fetchedAt:
                CarbonImmutable::now(),

            responseId:
                'FAKE-EMPTY-PAGE'
        );
    }
}