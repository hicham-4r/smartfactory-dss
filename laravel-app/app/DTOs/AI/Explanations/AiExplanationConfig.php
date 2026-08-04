<?php

namespace App\DTOs\AI\Explanations;

use App\Exceptions\AI\AiConfigurationException;

final readonly class AiExplanationConfig
{
    public function __construct(
        public string $baseUrl,
        public string $token,
        public bool $verifyTls,
        public string $endpoint,
        public int $connectTimeoutSeconds,
        public int $timeoutSeconds,
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

        if (
            strlen($token) < 32
            || preg_match('/[\x00-\x1F\x7F]/', $token) === 1
        ) {
            throw AiConfigurationException::invalidSetting(
                'token',
                'The internal token must contain at least 32 safe characters.',
            );
        }

        $verifyTls = self::booleanSetting(
            $settings,
            'verify_tls',
            true,
        );

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
            5,
            180,
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
            endpoint: self::safeRelativeEndpoint(
                'endpoint',
                self::requiredString($settings, 'endpoint', 2048),
            ),
            connectTimeoutSeconds: $connectTimeout,
            timeoutSeconds: $timeout,
            maximumRequestBytes: self::integerSetting(
                $settings,
                'maximum_request_bytes',
                4096,
                1048576,
            ),
            maximumResponseBytes: self::integerSetting(
                $settings,
                'maximum_response_bytes',
                4096,
                1048576,
            ),
            userAgent: self::safeHeaderValue(
                'user_agent',
                self::requiredString($settings, 'user_agent', 200),
            ),
            logChannel: self::requiredString(
                $settings,
                'log_channel',
                100,
            ),
        );
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

        return (int) $value;
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

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE,
        );

        if ($validated === null) {
            throw AiConfigurationException::invalidSetting(
                $key,
                'A Boolean value is required.',
            );
        }

        return $validated;
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

        if (! is_array($parts) || ! isset($parts['host'])) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL must contain a host.',
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || ($scheme !== 'https' && ! $allowInsecureTransport)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw AiConfigurationException::invalidSetting(
                'base_url',
                'The base URL is not allowed for this environment.',
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

    private static function safeHeaderValue(
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
