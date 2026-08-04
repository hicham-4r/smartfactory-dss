<?php

namespace App\Enums\ERP;

enum ErpRunLogType: string
{
    case Production = 'production';
    case Setup = 'setup';
    case Cleaning = 'cleaning';
    case Quality = 'quality';
    case Maintenance = 'maintenance';
    case Comment = 'comment';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Setup => 'Setup',
            self::Cleaning => 'Cleaning',
            self::Quality => 'Quality',
            self::Maintenance => 'Maintenance',
            self::Comment => 'Comment',
        };
    }
}