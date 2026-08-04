<?php

namespace App\Enums\ERP;

enum ErpNonconformitySeverity: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Minor',
            self::Major => 'Major',
            self::Critical => 'Critical',
        };
    }
}