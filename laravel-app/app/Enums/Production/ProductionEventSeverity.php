<?php

namespace App\Enums\Production;

enum ProductionEventSeverity: string
{
    case Information = 'information';
    case Warning = 'warning';
    case Critical = 'critical';

    public function requiresImmediateAttention(): bool
    {
        return $this === self::Critical;
    }

    public function label(): string
    {
        return match ($this) {
            self::Information => 'Information',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }
}