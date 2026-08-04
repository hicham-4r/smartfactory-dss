<?php

namespace Tests\Feature\Api;

use App\Models\ErpOperatorAssignment;
use App\Models\ErpProductionLine;
use App\Models\ErpShift;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpOperatorAssignmentApiTest extends TestCase
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

    public function test_operator_assignments_endpoint_requires_api_token(): void
    {
        $this->getJson('/api/operator-assignments')
            ->assertUnauthorized();

        $this->withHeaders([
            'X-ERP-Token' => 'incorrect-token',
            'Accept' => 'application/json',
        ])
            ->getJson('/api/operator-assignments')
            ->assertUnauthorized();
    }

    public function test_operator_assignments_endpoint_returns_canonical_paginated_data(): void
    {
        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/operator-assignments?per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonCount(18, 'data')
            ->assertJsonPath('meta.total', 18)
            ->assertJsonPath(
                'meta.data_source',
                'simulated'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'external_id',
                        'operator_external_id',
                        'production_line_external_id',
                        'shift_external_id',
                        'valid_from',
                        'valid_until',
                        'assigned_from',
                        'assigned_until',
                        'role_on_line',
                        'is_primary',
                        'is_active',
                        'operator' => [
                            'external_id',
                            'employee_code',
                            'full_name',
                        ],
                        'production_line' => [
                            'external_id',
                            'code',
                            'name',
                        ],
                        'shift' => [
                            'external_id',
                            'code',
                            'name',
                        ],
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $first = $response->json('data.0');

        $this->assertSame(
            'OA-001',
            $first['external_id']
        );

        $this->assertSame(
            $first['operator']['external_id'],
            $first['operator_external_id']
        );

        $this->assertSame(
            $first['production_line']['external_id'],
            $first['production_line_external_id']
        );

        $this->assertSame(
            $first['shift']['external_id'],
            $first['shift_external_id']
        );

        $this->assertSame(
            $first['assigned_from'],
            $first['valid_from']
        );

        $this->assertSame(
            18,
            ErpOperatorAssignment::query()->count()
        );
    }

    public function test_operator_assignments_can_be_filtered_by_line_and_shift(): void
    {
        $assignment = ErpOperatorAssignment::query()
            ->with([
                'productionLine',
                'shift',
            ])
            ->firstOrFail();

        $expectedCount = ErpOperatorAssignment::query()
            ->where(
                'production_line_id',
                $assignment->production_line_id
            )
            ->where(
                'shift_id',
                $assignment->shift_id
            )
            ->count();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/operator-assignments'
                .'?line_code='
                .urlencode(
                    $assignment->productionLine->code
                )
                .'&shift_code='
                .urlencode(
                    $assignment->shift->code
                )
                .'&per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                $expectedCount
            );

        foreach (
            $response->json('data')
            as $record
        ) {
            $this->assertSame(
                $assignment->productionLine->code,
                $record['production_line']['code']
            );

            $this->assertSame(
                $assignment->shift->code,
                $record['shift']['code']
            );
        }
    }

    public function test_operator_assignments_search_matches_operator_code(): void
    {
        $assignment = ErpOperatorAssignment::query()
            ->with('operator')
            ->firstOrFail();

        $response = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/operator-assignments'
                .'?search='
                .urlencode(
                    $assignment->operator->employee_code
                )
                .'&per_page=100'
            );

        $response->assertOk();

        $this->assertGreaterThan(
            0,
            $response->json('meta.total')
        );

        $employeeCodes = collect(
            $response->json('data')
        )->pluck('operator.employee_code');

        $this->assertTrue(
            $employeeCodes->contains(
                $assignment->operator->employee_code
            )
        );
    }

    public function test_operator_assignments_support_active_and_incremental_filters(): void
    {
        $activeResponse = $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/operator-assignments'
                .'?active=1&per_page=100'
            );

        $activeResponse
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                ErpOperatorAssignment::query()
                    ->where('is_active', true)
                    ->count()
            );

        foreach (
            $activeResponse->json('data')
            as $record
        ) {
            $this->assertTrue(
                $record['is_active']
            );
        }

        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/operator-assignments'
                .'?updated_since='
                .'2099-01-01T00%3A00%3A00Z'
            )
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_operator_assignments_reject_invalid_pagination(): void
    {
        $this
            ->withHeaders($this->apiHeaders())
            ->getJson(
                '/api/operator-assignments?per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }
}
