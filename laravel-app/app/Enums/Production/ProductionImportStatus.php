<?php

namespace App\Enums\Production;

enum ProductionImportStatus: string
{
    case NotApplicable = 'not_applicable';
    case Pending = 'pending';
    case Imported = 'imported';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function isSuccessful(): bool
    {
        return in_array(
            $this,
            [
                self::Imported,
                self::Skipped,
            ],
            true
        );
    }

    public function hasFailed(): bool
    {
        return $this === self::Failed;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Not applicable',
            self::Pending => 'Pending',
            self::Imported => 'Imported',
            self::Skipped => 'Skipped',
            self::Failed => 'Failed',
        };
    }
}