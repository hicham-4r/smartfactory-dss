<?php

namespace App\Enums\ERP;

enum ErpSyncResourceStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed,
            self::Failed,
            self::Skipped => true,

            self::Pending,
            self::Running => false,
        };
    }
}