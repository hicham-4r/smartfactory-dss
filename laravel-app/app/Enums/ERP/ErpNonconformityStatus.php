<?php

namespace App\Enums\ERP;

enum ErpNonconformityStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Corrected = 'corrected';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Investigating => 'Investigating',
            self::Corrected => 'Corrected',
            self::Closed => 'Closed',
        };
    }

    public function isResolved(): bool
    {
        return in_array(
            $this,
            [
                self::Corrected,
                self::Closed,
            ],
            true
        );
    }
}