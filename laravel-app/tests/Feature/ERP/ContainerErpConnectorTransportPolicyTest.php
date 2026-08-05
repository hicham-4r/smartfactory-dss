<?php

namespace Tests\Feature\ERP;

use App\DTOs\ERP\SimulatedSageConnectorConfig;
use App\Exceptions\ERP\ErpConfigurationException;
use Tests\TestCase;

class ContainerErpConnectorTransportPolicyTest extends TestCase
{
    public function test_container_environment_allows_private_internal_http_erp_url(): void
    {
        $this->app->instance(
            'env',
            'container'
        );

        config()->set(
            'erp.connectors.simulated_sage',
            $this->settings()
        );

        $this->app->forgetInstance(
            SimulatedSageConnectorConfig::class
        );

        $configuration = $this->app->make(
            SimulatedSageConnectorConfig::class
        );

        $this->assertSame(
            'http://nginx:8082',
            $configuration->baseUrl
        );

        $this->assertFalse(
            $configuration->verifyTls
        );
    }

    public function test_production_environment_still_rejects_http_erp_url(): void
    {
        $this->app->instance(
            'env',
            'production'
        );

        config()->set(
            'erp.connectors.simulated_sage',
            $this->settings()
        );

        $this->app->forgetInstance(
            SimulatedSageConnectorConfig::class
        );

        $this->expectException(
            ErpConfigurationException::class
        );

        $this->app->make(
            SimulatedSageConnectorConfig::class
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $settings = config(
            'erp.connectors.simulated_sage',
            []
        );

        $this->assertIsArray(
            $settings
        );

        $settings['base_url'] =
            'http://nginx:8082';

        $settings['token'] =
            str_repeat('a', 64);

        $settings['verify_tls'] =
            false;

        return $settings;
    }
}
