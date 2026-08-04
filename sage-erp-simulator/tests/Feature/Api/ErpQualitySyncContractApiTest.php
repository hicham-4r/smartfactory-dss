<?php

namespace Tests\Feature\Api;

use App\Models\ErpQualityInspection;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductionOperationalDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpQualityLotReleaseDataSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpQualitySyncContractApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_TOKEN =
        'test-erp-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' =>
                self::API_TOKEN,
        ]);

        $this->seed([
            ErpReferenceDataSeeder::class,
            ErpOperationalMasterDataSeeder::class,
            ErpProductCatalogSeeder::class,
            ErpProductionOperationalDataSeeder::class,
            ErpQualityLotReleaseDataSeeder::class,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-ERP-Token' =>
                self::API_TOKEN,

            'Accept' =>
                'application/json',
        ];
    }

    public function test_canonical_quality_endpoints_require_erp_token(): void
    {
        foreach (
            [
                '/api/finished-lots',
                '/api/inspections',
                '/api/nonconformities',
            ]
            as $endpoint
        ) {
            $this
                ->getJson($endpoint)
                ->assertUnauthorized();
        }
    }

    public function test_finished_lots_endpoint_returns_canonical_sync_payload(): void
    {
        $response = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/finished-lots'
                .'?per_page=5'
            );

        $response
            ->assertOk()
            ->assertHeader(
                'X-Data-Source',
                'simulated'
            )
            ->assertJsonCount(
                5,
                'data'
            )
            ->assertJsonPath(
                'meta.total',
                810
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'lot_number',
                        'batch_external_id',
                        'product_external_id',
                        'status',
                        'produced_at',
                        'expiry_date',
                        'produced_quantity',
                        'released_quantity',
                        'rejected_quantity',
                        'quantity_unit',
                        'released_at',
                        'released_by_external_id',
                        'release_notes',
                        'source_version',
                        'source_updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach (
            $response->json('data')
            as $lot
        ) {
            $this->assertNotEmpty(
                $lot['batch_external_id']
            );

            $this->assertNotEmpty(
                $lot['product_external_id']
            );

            $this->assertContains(
                $lot['status'],
                [
                    'released',
                    'blocked',
                    'rejected',
                ]
            );

            $this->assertLessThanOrEqual(
                (float) $lot[
                    'produced_quantity'
                ],

                (float) $lot[
                    'released_quantity'
                ]
                + (float) $lot[
                    'rejected_quantity'
                ]
            );

            $this->assertSame(
                'bottles',
                $lot['quantity_unit']
            );

            $this->assertSame(
                1,
                $lot['source_version']
            );
        }
    }

    public function test_inspections_endpoint_returns_canonical_sync_payload(): void
    {
        $response = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/inspections'
                .'?per_page=5'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                5,
                'data'
            )
            ->assertJsonPath(
                'meta.total',
                810
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'inspection_number',
                        'batch_external_id',
                        'finished_lot_external_id',
                        'inspector_external_id',
                        'inspection_type',
                        'result',
                        'inspected_at',
                        'sample_size',
                        'passed_quantity',
                        'failed_quantity',
                        'notes',
                        'source_version',
                        'source_updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach (
            $response->json('data')
            as $inspection
        ) {
            $this->assertNotEmpty(
                $inspection[
                    'batch_external_id'
                ]
            );

            $this->assertNotEmpty(
                $inspection[
                    'finished_lot_external_id'
                ]
            );

            $this->assertContains(
                $inspection['result'],
                [
                    'passed',
                    'failed',
                ]
            );

            $this->assertNotEmpty(
                $inspection['inspected_at']
            );

            /*
             * The simulator has no stable inspector external ID and no
             * physical sample-count field.
             */
            $this->assertNull(
                $inspection[
                    'inspector_external_id'
                ]
            );

            $this->assertNull(
                $inspection['sample_size']
            );
        }
    }

    public function test_nonconformities_endpoint_derives_stable_records_from_failed_inspections(): void
    {
        $expected =
            ErpQualityInspection::query()
                ->whereNotNull(
                    'nonconformity_code'
                )
                ->where(
                    'result',
                    'failed'
                )
                ->count();

        $this->assertGreaterThan(
            0,
            $expected
        );

        $response = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/nonconformities'
                .'?per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                $expected
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'nonconformity_number',
                        'inspection_external_id',
                        'batch_external_id',
                        'severity',
                        'status',
                        'category',
                        'description',
                        'detected_at',
                        'corrected_at',
                        'corrective_action',
                        'source_version',
                        'source_updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $externalIds = [];

        foreach (
            $response->json('data')
            as $nonconformity
        ) {
            $this->assertStringStartsWith(
                'nonconformity-',
                $nonconformity[
                    'external_id'
                ]
            );

            $this->assertNotEmpty(
                $nonconformity[
                    'inspection_external_id'
                ]
            );

            $this->assertNotEmpty(
                $nonconformity[
                    'batch_external_id'
                ]
            );

            $this->assertContains(
                $nonconformity[
                    'severity'
                ],
                [
                    'minor',
                    'major',
                    'critical',
                ]
            );

            $this->assertSame(
                'open',
                $nonconformity['status']
            );

            $this->assertNull(
                $nonconformity[
                    'corrected_at'
                ]
            );

            $externalIds[] =
                $nonconformity[
                    'external_id'
                ];
        }

        $this->assertSame(
            count($externalIds),
            count(
                array_unique(
                    $externalIds
                )
            )
        );
    }

    public function test_canonical_quality_endpoints_support_incremental_filtering(): void
    {
        foreach (
            [
                '/api/finished-lots',
                '/api/inspections',
                '/api/nonconformities',
            ]
            as $endpoint
        ) {
            $this
                ->withHeaders(
                    $this->apiHeaders()
                )
                ->getJson(
                    $endpoint
                    .'?updated_since='
                    .'2099-01-01T00%3A00%3A00Z'
                    .'&per_page=100'
                )
                ->assertOk()
                ->assertJsonCount(
                    0,
                    'data'
                )
                ->assertJsonPath(
                    'meta.total',
                    0
                );
        }
    }
}
