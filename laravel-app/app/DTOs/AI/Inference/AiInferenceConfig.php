<?php

namespace App\DTOs\AI\Inference;

use App\Exceptions\AI\AiConfigurationException;

final readonly class AiInferenceConfig
{
    public function __construct(
        public string $baseUrl,
        public string $token,
        public bool $verifyTls,
        public string $modelsEndpoint,
        public string $forecastEndpoint,
        public string $anomalyEndpoint,
        public string $maintenanceRiskEndpoint,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
        public int $retryAttempts,
        public int $retryDelayMilliseconds,
        public int $maximumRequestBytes,
        public int $maximumResponseBytes,
        public string $userAgent,
        public string $logChannel,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromArray(
        array $settings,
        bool $allowInsecureTransport = false,
    ): self {
        $baseUrl = self::validatedBaseUrl(
            self::requiredString($settings, 'base_url', 2048),
            $allowInsecureTransport,
        );

        $token = self::requiredString($settings, 'token', 4096);

        if (strlen($token) < 32) {
            throw AiConfigurationException::invalidSetting(
                'token',
                'The internal token must contain at least 32 characters.',
            );
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $token) === 1) {
            throw AiConfigurationException::invalidSetting(
                'token',
                'The internal token must not contain control characters.',
            );
        }

        $verifyTls = self::booleanSetting($settings, 'verify_tls', true);

        if (! $verifyTls && ! $allowInsecureTransport) {
            throw AiConfigurationException::invalidSetting(
                'verify_tls',
                'TLS verification cannot be disabled in this environment.',
            );
        }

        $connectTimeout = self::integerSetting(
            $settings,
            'connect_timeout_seconds',
            1,
            30,
        );
        $timeout = self::integerSetting(
            $settings,
            'timeout_seconds',
            1,
            120,
        );

        if ($connectTimeout > $timeout) {
            throw AiConfigurationException::invalidSetting(
                'connect_timeout_seconds',
                'The connection timeout cannot exceed the total timeout.',
            );
        }

        return new self(
            baseUrl: $baseUrl,
            token: $token,
            verifyTls: $verifyTls,
            modelsEndpoint: self::safeRelativeEndpoint(
                'models_endpoint',
                self::requiredString($settings, 'models_endpoint', 2048),
            ),
            forecastEndpoint: self::safeRelativeEndpoint(
                'forecast_endpoint',
                self::requiredString($settings, 'forecast_endpoint', 2048),
            ),
            anomalyEndpoint: self::safeRelativeEndpoint(
                'anomaly_endpoint',
                self::requiredString($settings, 'anomaly_endpoint', 2048),
            ),
            maintenanceRiskEndpoint: self::safeRelativeEndpoint(
                'maintenance_risk_endpoint',
                self::requiredString($settings, 'maintenance_risk_endpoint', 2048),
            ),
            connectTimeoutSeconds: $connectTimeout,
            timeoutSeconds: $timeout,
            retryAttempts: self::integerSetting(
                $settings,
                'retry_attempts',
                1,
                3,
            ),
            retryDelayMilliseconds: self::integerSetting(
                $settings,
                'retry_delay_milliseconds',
                0,
                5000,
            ),
            maximumRequestBytes: self::integerSetting(
                $settings,
                'maximum_request_bytes',
                1024,
                10485760,
            ),
            maximumResponseBytes: self::integerSetting(
                $settings,
                'maximum_response_bytes',
                1024,
                10485760,
            ),
            userAgent: self::validatedHeaderValue(
                'user_agent',
                self::requiredString($settings, 'user_agent', 200),
            ),
            logChannel: self::requiredString($settings, 'log_channel', 100),
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
            'models_endpoint' => $this->modelsEndpoint,
            'forecast_endpoint' => $this->forecastEndpoint,
            'anomaly_endpoint' => $this->anomalyEndpoint,
            'maintenance_risk_endpoint' => $this->maintenanceRiskEndpoint,
            'connect_timeout_seconds' => $this->connectTimeoutSeconds,
            'timeout_seconds' => $this->timeoutSeconds,
            'retry_attempts' => $this->retryAttempts,
            'maximum_request_bytes' => $this->maximumRequestBytes,
            'maximum_response_bytes' => $this->maximumResponseBytes,
            'user_agent' => $this->userAgent,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function requiredString(
        array $settings,
        string $key,
        int $maximumLength,
    ): string {
        $value = $settings[$key] ?? null;

        if (! is_string($value)) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'A string value is required.',
            );
        }

        $value = trim($value);

        if ($value === '' || strlen($value) > $maximumLength) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'The value is empty or exceeds the allowed length.',
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function booleanSetting(
        array $settings,
        string $key,
        bool $default,
    ): bool {
        $value = $settings[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );

        if ($normalized === null) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'A boolean value is required.',
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function integerSetting(
        array $settings,
        string $key,
        int $minimum,
        int $maximum,
    ): int {
        $value = filter_var(
            $settings[$key] ?? null,
            FILTER_VALIDATE_INT,
        );

        if (
            $value === false
            || $value < $minimum
            || $value > $maximum
        ) {
            throw AiConfigurationException::invalidSetting(
                $key,
                "The value must be an integer between {$minimum} and {$maximum}.",
            );
        }

        return $value;
    }

    private static function validatedBaseUrl(
        string $baseUrl,
        bool $allowInsecureTransport,
    ): string {
        $baseUrl = rtrim($baseUrl, '/');

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL must be valid.',
            );
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts)) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL could not be parsed.',
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'Only HTTP and HTTPS URLs are supported.',
            );
        }

        if ($scheme !== 'https' && ! $allowInsecureTransport) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'HTTPS is required outside explicitly allowed environments.',
            );
        }

        if (
            isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! isset($parts['host'])
        ) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'Credentials, query strings and fragments are not allowed.',
            );
        }

        return $baseUrl;
    }

    private static function safeRelativeEndpoint(
        string $key,
        string $endpoint,
    ): string {
        if (
            ! str_starts_with($endpoint, '/')
            || str_starts_with($endpoint, '//')
            || str_contains($endpoint, '?')
            || str_contains($endpoint, '#')
            || preg_match('/[\x00-\x1F\x7F]/', $endpoint) === 1
        ) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'A safe relative endpoint beginning with one slash is required.',
            );
        }

        return $endpoint;
    }

    private static function validatedHeaderValue(
        string $key,
        string $value,
    ): string {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'Control characters are not allowed.',
            );
        }

        return $value;
    }
}
