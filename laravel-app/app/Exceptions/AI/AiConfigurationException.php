<?php

namespace App\Exceptions\AI;

use RuntimeException;

final class AiConfigurationException extends RuntimeException
{
    public static function invalidSetting(
        string $setting,
        string $reason
    ): self {
        return new self(
            "Invalid AI service setting [{$setting}]. {$reason}"
        );
    }

    public static function unsupportedDriver(
        string $driver
    ): self {
        return new self(
            "The AI service driver [{$driver}] is not supported."
        );
    }
}
