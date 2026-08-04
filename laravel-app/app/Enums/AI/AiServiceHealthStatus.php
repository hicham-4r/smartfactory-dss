<?php

namespace App\Enums\AI;

enum AiServiceHealthStatus: string
{
    case Available = 'available';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
    case NotConfigured = 'not_configured';

    public function isOperational(): bool
    {
        return $this === self::Available;
    }

    public function needsAttention(): bool
    {
        return in_array(
            $this,
            [
                self::Degraded,
                self::Unavailable,
            ],
            true
        );
    }
}
