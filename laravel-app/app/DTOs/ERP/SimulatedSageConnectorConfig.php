<?php

namespace App\DTOs\ERP;

use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpConfigurationException;

final readonly class SimulatedSageConnectorConfig
{
    /**
     * @param array<string, string> $endpoints
     */
    public function __construct(
        public string $sourceSystem,
        public string $baseUrl,
        public string $token,
        public bool $verifyTls,
        public string $healthEndpoint,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public int $retryAttempts,
        public int $retryDelayMilliseconds,
        public int $retryMaximumDelayMilliseconds,
        public int $pageSize,
        public int $maximumPageSize,
        public int $maximumResponseBytes,
        public string $userAgent,
        public array $endpoints,
        public string $logChannel
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function fromArray(
        array $settings,
        bool $allowInsecureTls = false
    ): self {
        $sourceSystem = self::requiredString(
            settings: $settings,
            key: 'source_system',
            maximumLength: 50
        );

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_-]*$/',
                $sourceSystem
            ) !== 1
        ) {
            throw ErpConfigurationException::invalidSetting(
                'source_system',
                'The source system must use lowercase letters, numbers, underscores, or hyphens.'
            );
        }

        $baseUrl = self::validatedBaseUrl(
            self::requiredString(
                settings: $settings,
                key: 'base_url',
                maximumLength: 2048
            ),
            $allowInsecureTls
        );

        $token = self::requiredString(
            settings: $settings,
            key: 'token',
            maximumLength: 4096
        );

        if (strlen($token) < 16) {
            throw ErpConfigurationException::invalidSetting(
                'token',
                'The ERP token must contain at least 16 characters.'
            );
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $token
            ) === 1
        ) {
            throw ErpConfigurationException::invalidSetting(
                'token',
                'The ERP token must not contain control characters.'
            );
        }

        $verifyTls = self::booleanSetting(
            settings: $settings,
            key: 'verify_tls',
            default: true
        );

        if (
            ! $verifyTls
            && ! $allowInsecureTls
        ) {
            throw ErpConfigurationException::invalidSetting(
                'verify_tls',
                'TLS verification cannot be disabled in this environment.'
            );
        }

        $healthEndpoint = self::safeRelativeEndpoint(
            setting:
                'health_endpoint',

            endpoint:
                self::requiredString(
                    settings: $settings,
                    key: 'health_endpoint',
                    maximumLength: 2048
                )
        );

        $connectTimeoutSeconds =
            self::integerSetting(
                settings: $settings,
                key: 'connect_timeout_seconds',
                minimum: 1,
                maximum: 60
            );

        $timeoutSeconds =
            self::integerSetting(
                settings: $settings,
                key: 'timeout_seconds',
                minimum: 1,
                maximum: 300
            );

        if (
            $connectTimeoutSeconds
            > $timeoutSeconds
        ) {
            throw ErpConfigurationException::invalidSetting(
                'connect_timeout_seconds',
                'The connection timeout cannot exceed the total request timeout.'
            );
        }

        $retryAttempts =
            self::integerSetting(
                settings: $settings,
                key: 'retry_attempts',
                minimum: 1,
                maximum: 10
            );

        $retryDelayMilliseconds =
            self::integerSetting(
                settings: $settings,
                key: 'retry_delay_milliseconds',
                minimum: 0,
                maximum: 60000
            );

        $retryMaximumDelayMilliseconds =
            self::integerSetting(
                settings: $settings,
                key: 'retry_maximum_delay_milliseconds',
                minimum: 0,
                maximum: 300000
            );

        if (
            $retryMaximumDelayMilliseconds
            < $retryDelayMilliseconds
        ) {
            throw ErpConfigurationException::invalidSetting(
                'retry_maximum_delay_milliseconds',
                'The maximum retry delay cannot be lower than the initial retry delay.'
            );
        }

        /*
         * The separate Sage simulator accepts per_page values from
         * 1 through 100.
         */
        $maximumPageSize =
            self::integerSetting(
                settings: $settings,
                key: 'maximum_page_size',
                minimum: 1,
                maximum: 100
            );

        $pageSize =
            self::integerSetting(
                settings: $settings,
                key: 'page_size',
                minimum: 1,
                maximum: $maximumPageSize
            );

        $maximumResponseBytes =
            self::integerSetting(
                settings: $settings,
                key: 'maximum_response_bytes',
                minimum: 1024,
                maximum: 52428800
            );

        $userAgent = self::requiredString(
            settings: $settings,
            key: 'user_agent',
            maximumLength: 200
        );

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $userAgent
            ) === 1
        ) {
            throw ErpConfigurationException::invalidSetting(
                'user_agent',
                'The ERP user agent must not contain control characters.'
            );
        }

        $endpoints =
            self::validatedEndpoints(
                $settings['endpoints']
                    ?? null
            );

        $configuredLogChannel =
            $settings['log_channel']
            ?? config(
                'erp.logging.channel',
                'stack'
            );

        if (! is_string($configuredLogChannel)) {
            throw ErpConfigurationException::invalidSetting(
                'log_channel',
                'The ERP logging channel must be a string.'
            );
        }

        $logChannel = trim(
            $configuredLogChannel
        );

        if (
            $logChannel === ''
            || strlen($logChannel) > 100
        ) {
            throw ErpConfigurationException::invalidSetting(
                'log_channel',
                'The ERP logging channel must be a non-empty value of at most 100 characters.'
            );
        }

        return new self(
            sourceSystem:
                $sourceSystem,

            baseUrl:
                $baseUrl,

            token:
                $token,

            verifyTls:
                $verifyTls,

            healthEndpoint:
                $healthEndpoint,

            connectTimeoutSeconds:
                $connectTimeoutSeconds,

            timeoutSeconds:
                $timeoutSeconds,

            retryAttempts:
                $retryAttempts,

            retryDelayMilliseconds:
                $retryDelayMilliseconds,

            retryMaximumDelayMilliseconds:
                $retryMaximumDelayMilliseconds,

            pageSize:
                $pageSize,

            maximumPageSize:
                $maximumPageSize,

            maximumResponseBytes:
                $maximumResponseBytes,

            userAgent:
                $userAgent,

            endpoints:
                $endpoints,

            logChannel:
                $logChannel
        );
    }

    public function supports(
        ErpResource $resource
    ): bool {
        return array_key_exists(
            $resource->value,
            $this->endpoints
        );
    }

    public function endpointFor(
        ErpResource $resource
    ): string {
        $endpoint =
            $this->endpoints[
                $resource->value
            ]
            ?? null;

        if (
            ! is_string($endpoint)
            || $endpoint === ''
        ) {
            throw ErpConfigurationException::unsupportedResource(
                $resource
            );
        }

        return $endpoint;
    }

    /**
     * Return only non-secret diagnostic values.
     *
     * @return array<string, mixed>
     */
    public function safeSummary(): array
    {
        return [
            'source_system' =>
                $this->sourceSystem,

            'base_url' =>
                $this->baseUrl,

            'verify_tls' =>
                $this->verifyTls,

            'health_endpoint' =>
                $this->healthEndpoint,

            'connect_timeout_seconds' =>
                $this->connectTimeoutSeconds,

            'timeout_seconds' =>
                $this->timeoutSeconds,

            'retry_attempts' =>
                $this->retryAttempts,

            'page_size' =>
                $this->pageSize,

            'maximum_page_size' =>
                $this->maximumPageSize,

            'supported_resources' =>
                array_keys(
                    $this->endpoints
                ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function validatedEndpoints(
        mixed $configuredEndpoints
    ): array {
        if (
            ! is_array($configuredEndpoints)
            || $configuredEndpoints === []
        ) {
            throw ErpConfigurationException::invalidSetting(
                'endpoints',
                'At least one ERP endpoint must be configured.'
            );
        }

        $normalized = [];

        foreach (
            $configuredEndpoints
            as $resourceName => $endpoint
        ) {
            if (! is_string($resourceName)) {
                throw ErpConfigurationException::invalidSetting(
                    'endpoints',
                    'Every ERP endpoint must use a string resource name.'
                );
            }

            $resourceName = trim(
                $resourceName
            );

            $resource =
                ErpResource::tryFrom(
                    $resourceName
                );

            if ($resource === null) {
                throw ErpConfigurationException::invalidSetting(
                    'endpoints.'.$resourceName,
                    'The configured ERP resource is unknown.'
                );
            }

            if (! is_string($endpoint)) {
                throw ErpConfigurationException::invalidSetting(
                    'endpoints.'.$resourceName,
                    'The ERP endpoint must be a string.'
                );
            }

            $normalized[
                $resource->value
            ] = self::safeRelativeEndpoint(
                setting:
                    'endpoints.'
                    .$resource->value,

                endpoint:
                    $endpoint
            );
        }

        /*
         * A connector advertises only resources exposed by its source.
         * Local DSS resources such as RunLogs may therefore be absent.
         */
        return $normalized;
    }

    private static function validatedBaseUrl(
        string $baseUrl,
        bool $allowInsecureTls
    ): string {
        $baseUrl = rtrim(
            trim($baseUrl),
            '/'
        );

        if (
            filter_var(
                $baseUrl,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            throw ErpConfigurationException::invalidSetting(
                'base_url',
                'The ERP base URL must be a valid URL.'
            );
        }

        $parts = parse_url(
            $baseUrl
        );

        if (! is_array($parts)) {
            throw ErpConfigurationException::invalidSetting(
                'base_url',
                'The ERP base URL could not be parsed.'
            );
        }

        $scheme = strtolower(
            (string) (
                $parts['scheme']
                ?? ''
            )
        );

        if (
            $scheme !== 'https'
            && ! (
                $allowInsecureTls
                && $scheme === 'http'
            )
        ) {
            throw ErpConfigurationException::invalidSetting(
                'base_url',
                'The ERP base URL must use HTTPS.'
            );
        }

        if (
            ! isset($parts['host'])
            || trim(
                (string) $parts['host']
            ) === ''
        ) {
            throw ErpConfigurationException::invalidSetting(
                'base_url',
                'The ERP base URL must include a host.'
            );
        }

        if (
            isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw ErpConfigurationException::invalidSetting(
                'base_url',
                'Credentials must not be embedded in the ERP base URL.'
            );
        }

        if (
            isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw ErpConfigurationException::invalidSetting(
                'base_url',
                'The ERP base URL must not contain a query string or fragment.'
            );
        }

        return $baseUrl;
    }

    private static function safeRelativeEndpoint(
        string $setting,
        string $endpoint
    ): string {
        $endpoint = trim(
            $endpoint
        );

        if ($endpoint === '') {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint must not be empty.'
            );
        }

        if (strlen($endpoint) > 2048) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint is too long.'
            );
        }

        if (
            ! str_starts_with(
                $endpoint,
                '/'
            )
            || str_starts_with(
                $endpoint,
                '//'
            )
        ) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint must be a relative path beginning with one slash.'
            );
        }

        if (
            filter_var(
                $endpoint,
                FILTER_VALIDATE_URL
            ) !== false
        ) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint must not be an absolute URL.'
            );
        }

        $parts = parse_url(
            $endpoint
        );

        if (! is_array($parts)) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint could not be parsed.'
            );
        }

        if (
            isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint must remain relative to the configured base URL.'
            );
        }

        if (
            isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'ERP endpoint query strings and fragments must be supplied separately.'
            );
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $endpoint
            ) === 1
        ) {
            throw ErpConfigurationException::invalidSetting(
                $setting,
                'The ERP endpoint must not contain control characters.'
            );
        }

        return $endpoint;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function requiredString(
        array $settings,
        string $key,
        int $maximumLength
    ): string {
        $value =
            $settings[$key]
            ?? null;

        if (! is_string($value)) {
            throw ErpConfigurationException::invalidSetting(
                $key,
                'The setting must be a string.'
            );
        }

        $value = trim(
            $value
        );

        if ($value === '') {
            throw ErpConfigurationException::invalidSetting(
                $key,
                'The setting must not be empty.'
            );
        }

        if (strlen($value) > $maximumLength) {
            throw ErpConfigurationException::invalidSetting(
                $key,
                'The setting exceeds the allowed length.'
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function integerSetting(
        array $settings,
        string $key,
        int $minimum,
        int $maximum
    ): int {
        $value =
            $settings[$key]
            ?? null;

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' =>
                        $minimum,

                    'max_range' =>
                        $maximum,
                ],
            ]
        );

        if ($validated === false) {
            throw ErpConfigurationException::invalidSetting(
                $key,
                'The setting must be an integer between '
                .$minimum
                .' and '
                .$maximum
                .'.'
            );
        }

        return (int) $validated;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private static function booleanSetting(
        array $settings,
        string $key,
        bool $default
    ): bool {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = filter_var(
            $settings[$key],
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );

        if ($value === null) {
            throw ErpConfigurationException::invalidSetting(
                $key,
                'The setting must be a valid Boolean value.'
            );
        }

        return $value;
    }
}