<?php

namespace App\Enums\ERP;

enum ErpConnectorHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unavailable = 'unavailable';
    case Disabled = 'disabled';

    public function isAvailable(): bool
    {
        return in_array(
            $this,
            [
                self::Healthy,
                self::Degraded,
            ],
            true
        );
    }
}