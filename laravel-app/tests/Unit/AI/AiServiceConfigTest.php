<?php

namespace Tests\Unit\AI;

use App\DTOs\AI\AiServiceConfig;
use App\Exceptions\AI\AiConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiServiceConfigTest extends TestCase
{
    public function test_valid_local_configuration_is_normalized(): void
    {
        $configuration = AiServiceConfig::fromArray(
            settings: $this->settings(),
            allowInsecureTransport: true
        );

        self::assertSame(
            'http://127.0.0.1:8001',
            $configuration->baseUrl
        );

        self::assertSame(
            '/health/ready',
            $configuration->healthEndpoint
        );

        self::assertSame(
            '/internal/v1/contracts/analytics/validate',
            $configuration
                ->analyticsContractEndpoint
        );

        self::assertSame(
            1048576,
            $configuration
                ->maximumRequestBytes
        );

        self::assertArrayNotHasKey(
            'token',
            $configuration->safeSummary()
        );
    }

    public function test_http_is_rejected_without_explicit_internal_allowance(): void
    {
        $this->expectException(
            AiConfigurationException::class
        );

        AiServiceConfig::fromArray(
            settings: $this->settings(),
            allowInsecureTransport: false
        );
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_security_settings_are_rejected(
        string $key,
        mixed $value
    ): void {
        $settings = $this->settings();
        $settings[$key] = $value;

        $this->expectException(
            AiConfigurationException::class
        );

        AiServiceConfig::fromArray(
            settings: $settings,
            allowInsecureTransport: true
        );
    }

    /**
     * @return iterable<string, array{0:string,1:mixed}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'short token' => [
            'token',
            'too-short',
        ];

        yield 'absolute endpoint' => [
            'health_endpoint',
            'https://attacker.example/health',
        ];

        yield 'absolute analytics endpoint' => [
            'analytics_contract_endpoint',
            'https://attacker.example/contracts',
        ];

        yield 'small request limit' => [
            'maximum_request_bytes',
            100,
        ];

        yield 'excessive attempts' => [
            'retry_attempts',
            10,
        ];

        yield 'embedded credentials' => [
            'base_url',
            'http://user:password@127.0.0.1:8001',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        return [
            'base_url' =>
                'http://127.0.0.1:8001',
            'token' => str_repeat('a', 64),
            'verify_tls' => false,
            'health_endpoint' =>
                '/health/ready',
            'version_endpoint' =>
                '/version',
            'analytics_contract_endpoint' =>
                '/internal/v1/contracts/analytics/validate',
            'connect_timeout_seconds' => 2,
            'timeout_seconds' => 5,
            'retry_attempts' => 2,
            'retry_delay_milliseconds' => 0,
            'maximum_request_bytes' =>
                1048576,
            'maximum_response_bytes' =>
                262144,
            'user_agent' =>
                'SmartFactory-DSS/1.0',
            'log_channel' => 'stack',
        ];
    }
}
