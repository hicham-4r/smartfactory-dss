<?php

namespace Tests\Feature\Api;

use App\Models\ErpProduct;
use App\Models\ErpProductFamily;
use Database\Seeders\ErpOperationalMasterDataSeeder;
use Database\Seeders\ErpProductCatalogSeeder;
use Database\Seeders\ErpReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpProductFamilyApiTest extends TestCase
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
        ]);
    }

    public function test_product_family_endpoint_requires_api_token(): void
    {
        $this->getJson(
            '/api/product-families'
        )->assertUnauthorized();

        $this->withHeaders([
            'X-ERP-Token' =>
                'incorrect-token',

            'Accept' =>
                'application/json',
        ])
            ->getJson(
                '/api/product-families'
            )
            ->assertUnauthorized();
    }

    public function test_product_family_endpoint_returns_paginated_data(): void
    {
        $response = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/product-families'
                .'?per_page=100'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                10,
                'data'
            )
            ->assertJsonPath(
                'meta.total',
                10
            )
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
                        'description',
                        'is_active',
                        'products_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertSame(
            10,
            ErpProductFamily::query()
                ->count()
        );
    }

    public function test_product_families_support_search_and_active_filters(): void
    {
        $family = ErpProductFamily::query()
            ->where(
                'is_active',
                true
            )
            ->firstOrFail();

        $response = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/product-families'
                .'?search='
                .urlencode($family->code)
                .'&active=1'
                .'&per_page=100'
            );

        $response
            ->assertOk();

        $codes = collect(
            $response->json('data')
        )->pluck('code');

        $this->assertTrue(
            $codes->contains(
                $family->code
            )
        );

        foreach (
            $response->json('data')
            as $record
        ) {
            $this->assertTrue(
                (bool) $record[
                    'is_active'
                ]
            );
        }
    }

    public function test_product_payload_exposes_canonical_and_nested_family_external_id(): void
    {
        $product = ErpProduct::query()
            ->with('family')
            ->firstOrFail();

        $response = $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/products'
                .'?search='
                .urlencode($product->code)
                .'&per_page=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.external_id',
                $product->external_id
            )
            ->assertJsonPath(
                'data.0.product_family_external_id',
                $product
                    ->family
                    ->external_id
            )
            ->assertJsonPath(
                'data.0.family.external_id',
                $product
                    ->family
                    ->external_id
            );
    }

    public function test_product_family_incremental_filter_can_return_empty_page(): void
    {
        $this
            ->withHeaders(
                $this->apiHeaders()
            )
            ->getJson(
                '/api/product-families'
                .'?updated_since='
                .'2099-01-01T00%3A00%3A00Z'
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
}
