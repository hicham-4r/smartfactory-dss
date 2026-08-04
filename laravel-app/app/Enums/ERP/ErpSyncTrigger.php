<?php

namespace App\Enums\ERP;

enum ErpSyncTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case Retry = 'retry';
    case Recovery = 'recovery';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Scheduled => 'Scheduled',
            self::Retry => 'Retry',
            self::Recovery => 'Recovery',
        };
    }
}