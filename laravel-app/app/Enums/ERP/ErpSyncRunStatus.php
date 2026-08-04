<?php

namespace App\Enums\ERP;

enum ErpSyncRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';

    case CompletedWithErrors =
        'completed_with_errors';

    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed,
            self::CompletedWithErrors,
            self::Failed,
            self::Cancelled => true,

            self::Pending,
            self::Running => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }
}