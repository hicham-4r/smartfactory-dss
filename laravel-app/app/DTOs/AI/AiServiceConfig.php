<?php

namespace App\DTOs\AI;

use App\Exceptions\AI\AiConfigurationException;

final readonly class AiServiceConfig
{
    public function __construct(
        public string $baseUrl,
        public string $token,
        public bool $verifyTls,
        public string $healthEndpoint,
        public string $versionEndpoint,
        public string $analyticsContractEndpoint,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public int $retryAttempts,
        public int $retryDelayMilliseconds,
        public int $maximumRequestBytes,
        public int $maximumResponseBytes,
        public string $userAgent,
        public string $logChannel
    ) {
    }

    /**
     * @param array<string, mixed> $settings
     */
    public static function fromArray(
        array $settings,
        bool $allowInsecureTransport = false
    ): self {
        $baseUrl = self::validatedBaseUrl(
            self::requiredString(
                settings: $settings,
                key: 'base_url',
                maximumLength: 2048
            ),
            $allowInsecureTransport
        );

        $token = self::requiredString(
            settings: $settings,
            key: 'token',
            maximumLength: 4096
        );

        if (strlen($token) < 32) {
            throw AiConfigurationException::invalidSetting(
                'token',
                'The internal token must contain at least 32 characters.'
            );
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $token
            ) === 1
        ) {
            throw AiConfigurationException::invalidSetting(
                'token',
                'The internal token must not contain control characters.'
            );
        }

        $verifyTls = self::booleanSetting(
            settings: $settings,
            key: 'verify_tls',
            default: true
        );

        if (
            ! $verifyTls
            && ! $allowInsecureTransport
        ) {
            throw AiConfigurationException::invalidSetting(
                'verify_tls',
                'TLS verification cannot be disabled in this environment.'
            );
        }

        $healthEndpoint = self::safeRelativeEndpoint(
            'health_endpoint',
            self::requiredString(
                settings: $settings,
                key: 'health_endpoint',
                maximumLength: 2048
            )
        );

        $versionEndpoint = self::safeRelativeEndpoint(
            'version_endpoint',
            self::requiredString(
                settings: $settings,
                key: 'version_endpoint',
                maximumLength: 2048
            )
        );

        $analyticsContractEndpoint =
            self::safeRelativeEndpoint(
                'analytics_contract_endpoint',
                self::requiredString(
                    settings: $settings,
                    key:
                        'analytics_contract_endpoint',
                    maximumLength: 2048
                )
            );

        $connectTimeoutSeconds = self::integerSetting(
            settings: $settings,
            key: 'connect_timeout_seconds',
            minimum: 1,
            maximum: 30
        );

        $timeoutSeconds = self::integerSetting(
            settings: $settings,
            key: 'timeout_seconds',
            minimum: 1,
            maximum: 120
        );

        if ($connectTimeoutSeconds > $timeoutSeconds) {
            throw AiConfigurationException::invalidSetting(
                'connect_timeout_seconds',
                'The connection timeout cannot exceed the total timeout.'
            );
        }

        $retryAttempts = self::integerSetting(
            settings: $settings,
            key: 'retry_attempts',
            minimum: 1,
            maximum: 3
        );

        $retryDelayMilliseconds = self::integerSetting(
            settings: $settings,
            key: 'retry_delay_milliseconds',
            minimum: 0,
            maximum: 5000
        );

        $maximumRequestBytes =
            self::integerSetting(
                settings: $settings,
                key: 'maximum_request_bytes',
                minimum: 1024,
                maximum: 10485760
            );

        $maximumResponseBytes =
            self::integerSetting(
                settings: $settings,
                key: 'maximum_response_bytes',
                minimum: 1024,
                maximum: 10485760
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
            throw AiConfigurationException::invalidSetting(
                'user_agent',
                'The user agent must not contain control characters.'
            );
        }

        $logChannel = self::requiredString(
            settings: $settings,
            key: 'log_channel',
            maximumLength: 100
        );

        return new self(
            baseUrl: $baseUrl,
            token: $token,
            verifyTls: $verifyTls,
            healthEndpoint: $healthEndpoint,
            versionEndpoint: $versionEndpoint,
            analyticsContractEndpoint:
                $analyticsContractEndpoint,
            connectTimeoutSeconds: $connectTimeoutSeconds,
            timeoutSeconds: $timeoutSeconds,
            retryAttempts: $retryAttempts,
            retryDelayMilliseconds: $retryDelayMilliseconds,
            maximumRequestBytes:
                $maximumRequestBytes,
            maximumResponseBytes: $maximumResponseBytes,
            userAgent: $userAgent,
            logChannel: $logChannel
        );
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function safeSummary(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'verify_tls' => $this->verifyTls,
            'health_endpoint' => $this->healthEndpoint,
            'version_endpoint' => $this->versionEndpoint,
            'analytics_contract_endpoint' =>
                $this->analyticsContractEndpoint,
            'connect_timeout_seconds' => $this->connectTimeoutSeconds,
            'timeout_seconds' => $this->timeoutSeconds,
            'retry_attempts' => $this->retryAttempts,
            'maximum_request_bytes' =>
                $this->maximumRequestBytes,
            'maximum_response_bytes' => $this->maximumResponseBytes,
            'user_agent' => $this->userAgent,
        ];
    }

    private static function validatedBaseUrl(
        string $baseUrl,
        bool $allowInsecureTransport
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
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL must be valid.'
            );
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts)) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL could not be parsed.'
            );
        }

        $scheme = strtolower(
            (string) ($parts['scheme'] ?? '')
        );

        if (
            $scheme !== 'https'
            && ! (
                $allowInsecureTransport
                && $scheme === 'http'
            )
        ) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'HTTPS is required outside an explicitly trusted local or internal environment.'
            );
        }

        if (
            ! isset($parts['host'])
            || trim((string) $parts['host']) === ''
        ) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL must contain a host.'
            );
        }

        if (
            isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'Credentials, query strings, and fragments are not allowed.'
            );
        }

        return $baseUrl;
    }

    private static function safeRelativeEndpoint(
        string $setting,
        string $endpoint
    ): string {
        $endpoint = trim($endpoint);

        if (
            ! str_starts_with($endpoint, '/')
            || str_starts_with($endpoint, '//')
            || strlen($endpoint) > 2048
        ) {
            throw AiConfigurationException::invalidSetting(
                $setting,
                'The endpoint must be a relative path beginning with one slash.'
            );
        }

        $parts = parse_url($endpoint);

        if (
            ! is_array($parts)
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('/[\x00-\x1F\x7F]/', $endpoint) === 1
        ) {
            throw AiConfigurationException::invalidSetting(
                $setting,
                'The endpoint is not a safe relative path.'
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
        $value = $settings[$key] ?? null;

        if (! is_string($value)) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'The setting must be a string.'
            );
        }

        $value = trim($value);

        if (
            $value === ''
            || strlen($value) > $maximumLength
        ) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'The setting is empty or too long.'
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
        $validated = filter_var(
            $settings[$key] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => $minimum,
                    'max_range' => $maximum,
                ],
            ]
        );

        if ($validated === false) {
            throw AiConfigurationException::invalidSetting(
                $key,
                "The setting must be an integer between {$minimum} and {$maximum}."
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

        $validated = filter_var(
            $settings[$key],
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        );

        if ($validated === null) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'The setting must be Boolean.'
            );
        }

        return $validated;
    }
}
