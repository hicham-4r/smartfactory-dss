<?php

namespace Tests\Feature\Api;

use App\Models\ErpFinishedLotRelease;
use App\Models\ErpQualityInspection;
use App\Models\ErpQualityTestResult;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductionOperationalDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpQualityLotReleaseDataSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpQualityApiTest extends TestCase
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
            ErpQualityLotReleaseDataSeeder::class,
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

    public function test_quality_endpoints_filters_and_integrity(): void
    {
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

        /*
        |--------------------------------------------------------------------------
        | Quality inspections
        |--------------------------------------------------------------------------
        */

        $inspections = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-inspections?per_page=5'
            );

        $inspections
            ->assertOk()
            ->assertHeader(
                'X-Data-Source',
                'simulated'
            )
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 810)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'inspection_number',
                        'inspection_type',
                        'sampled_at',
                        'inspection_started_at',
                        'inspection_completed_at',
                        'inspector_name',
                        'status',
                        'result',
                        'overall_score_percent',
                        'is_late_arrival',
                        'source_updated_at',
                        'test_results_count',
                        'product',
                        'production_line',
                        'shift',
                        'production_batch',
                        'test_results',
                        'lot_release',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach ($inspections->json('data') as $inspection) {
            $this->assertSame(
                6,
                $inspection['test_results_count']
            );

            $this->assertCount(
                6,
                $inspection['test_results']
            );
        }

        $failedInspections = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-inspections'
                . '?result=failed'
                . '&per_page=100'
            );

        $failedInspections->assertOk();

        $this->assertGreaterThan(
            0,
            $failedInspections->json('meta.total')
        );

        foreach (
            $failedInspections->json('data')
            as $inspection
        ) {
            $this->assertSame(
                'failed',
                $inspection['result']
            );

            $this->assertNotNull(
                $inspection['nonconformity_code']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Quality-test results
        |--------------------------------------------------------------------------
        */

        $testResults = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-test-results?per_page=5'
            );

        $testResults
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 4860)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'test_code',
                        'test_name',
                        'test_category',
                        'numeric_value',
                        'text_value',
                        'unit',
                        'minimum_specification',
                        'maximum_specification',
                        'result',
                        'tested_at',
                        'quality_inspection',
                    ],
                ],
                'links',
                'meta',
            ]);

        $phTests = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-test-results'
                . '?test_code=PH'
                . '&per_page=100'
            );

        $phTests
            ->assertOk()
            ->assertJsonPath('meta.total', 810);

        foreach ($phTests->json('data') as $test) {
            $this->assertSame(
                'PH',
                $test['test_code']
            );
        }

        $failedTests = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-test-results'
                . '?test_result=failed'
                . '&per_page=100'
            );

        $failedTests->assertOk();

        $this->assertGreaterThan(
            0,
            $failedTests->json('meta.total')
        );

        foreach ($failedTests->json('data') as $test) {
            $this->assertSame(
                'failed',
                $test['result']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Finished-lot releases
        |--------------------------------------------------------------------------
        */

        $releases = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/finished-lot-releases?per_page=5'
            );

        $releases
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 810)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'release_number',
                        'lot_number',
                        'decision',
                        'warehouse_status',
                        'decision_at',
                        'released_at',
                        'released_by',
                        'quality_certificate_number',
                        'approved_quantity',
                        'blocked_quantity',
                        'rejected_quantity',
                        'expiry_date',
                        'decision_reason',
                        'is_late_arrival',
                        'source_updated_at',
                        'production_batch',
                        'quality_inspection',
                    ],
                ],
                'links',
                'meta',
            ]);

        foreach ($releases->json('data') as $release) {
            $totalDecisionQuantity =
                $release['approved_quantity']
                + $release['blocked_quantity']
                + $release['rejected_quantity'];

            $this->assertSame(
                $release['production_batch']
                    ['gross_quantity'],
                $totalDecisionQuantity
            );

            if ($release['decision'] === 'released') {
                $this->assertSame(
                    'available',
                    $release['warehouse_status']
                );

                $this->assertNotNull(
                    $release[
                        'quality_certificate_number'
                    ]
                );
            }
        }

        $releasedLots = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/finished-lot-releases'
                . '?decision=released'
                . '&per_page=100'
            );

        $releasedLots->assertOk();

        $this->assertGreaterThan(
            0,
            $releasedLots->json('meta.total')
        );

        foreach ($releasedLots->json('data') as $release) {
            $this->assertSame(
                'released',
                $release['decision']
            );

            $this->assertSame(
                'passed',
                $release['quality_inspection']
                    ['result']
            );

            $this->assertNotNull(
                $release['quality_certificate_number']
            );
        }

        $rejectedLots = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/finished-lot-releases'
                . '?decision=rejected'
                . '&per_page=100'
            );

        $rejectedLots->assertOk();

        $this->assertGreaterThan(
            0,
            $rejectedLots->json('meta.total')
        );

        foreach ($rejectedLots->json('data') as $release) {
            $this->assertSame(
                'rejected',
                $release['decision']
            );

            $this->assertSame(
                'failed',
                $release['quality_inspection']
                    ['result']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Late-arrival and incremental filters
        |--------------------------------------------------------------------------
        */

        $lateInspections = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-inspections'
                . '?is_late_arrival=1'
                . '&per_page=100'
            );

        $lateInspections->assertOk();

        $this->assertGreaterThan(
            0,
            $lateInspections->json('meta.total')
        );

        foreach (
            $lateInspections->json('data')
            as $inspection
        ) {
            $this->assertTrue(
                $inspection['is_late_arrival']
            );
        }

        $futureSync = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/finished-lot-releases'
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
                '/api/quality-inspections?result=invalid'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'result',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/finished-lot-releases'
                . '?decision=invalid'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'decision',
            ]);

        $this->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/quality-test-results?per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }
}