<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;

final class ErpConfigurationException extends ErpConnectorException
{
    public static function disabled(): self
    {
        return new self(
            message:
                'ERP integration is disabled.',

            retryable: false,

            safeContext: [
                'driver' => 'disabled',
            ]
        );
    }

    public static function unsupportedDriver(
        string $driver
    ): self {
        return new self(
            message:
                'The configured ERP connector driver is unsupported.',

            retryable: false,

            safeContext: [
                'driver' => $driver,
            ]
        );
    }

    public static function unsupportedResource(
        ErpResource $resource
    ): self {
        return new self(
            message:
                'The ERP connector does not support the requested resource.',

            retryable: false,
            resource: $resource
        );
    }

    public static function missingToken(): self
    {
        return new self(
            message:
                'The Simulated Sage ERP token is not configured.',

            retryable: false,

            safeContext: [
                'setting' =>
                    'ERP_SIMULATED_SAGE_TOKEN',
            ]
        );
    }

    public static function insecureTlsForbidden(): self
    {
        return new self(
            message:
                'Disabling ERP TLS verification is forbidden in this environment.',

            retryable: false,

            safeContext: [
                'setting' =>
                    'ERP_SIMULATED_SAGE_VERIFY_TLS',
            ]
        );
    }

    public static function invalidSetting(
        string $setting,
        string $reason
    ): self {
        return new self(
            message:
                'The ERP connector configuration is invalid: '
                .$reason,

            retryable: false,

            safeContext: [
                'setting' => $setting,
            ]
        );
    }
}