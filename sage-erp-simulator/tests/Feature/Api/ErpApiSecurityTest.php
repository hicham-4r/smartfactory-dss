<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ErpApiSecurityTest extends TestCase
{
    private const API_TOKEN = 'test-erp-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'erp.api_token' => self::API_TOKEN,
        ]);
    }

    public function test_health_is_public_and_all_data_routes_are_protected(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath(
                'service',
                'sage-erp-simulator'
            )
            ->assertJsonPath(
                'data_source',
                'simulated'
            )
            ->assertJsonPath('api_version', '1.0');

        $protectedEndpoints = [
            '/api/products',
            '/api/production-lines',
            '/api/machines',
            '/api/shifts',
            '/api/operators',
            '/api/production-orders',
            '/api/production-batches',
            '/api/production-records',
            '/api/downtime-events',
            '/api/machine-status-events',
            '/api/maintenance-history',
            '/api/quality-inspections',
            '/api/quality-test-results',
            '/api/finished-lot-releases',
        ];

        foreach ($protectedEndpoints as $endpoint) {
            $this->getJson($endpoint)
                ->assertUnauthorized()
                ->assertJson([
                    'message' =>
                        'Unauthorized simulated ERP API request.',

                    'error_code' => 'INVALID_ERP_TOKEN',
                ]);

            $this->withHeaders([
                'X-ERP-Token' => 'invalid-token',
                'Accept' => 'application/json',
            ])
                ->getJson($endpoint)
                ->assertUnauthorized()
                ->assertJsonPath(
                    'error_code',
                    'INVALID_ERP_TOKEN'
                );
        }
    }
}