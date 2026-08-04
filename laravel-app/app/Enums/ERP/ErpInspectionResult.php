<?php

namespace App\Enums\ERP;

enum ErpInspectionResult: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Conditional = 'conditional';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Conditional => 'Conditional',
        };
    }
}