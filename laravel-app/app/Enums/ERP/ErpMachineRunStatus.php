<?php

namespace App\Enums\ERP;

enum ErpMachineRunStatus: string
{
    case Planned = 'planned';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Stopped = 'stopped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Running => 'Running',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Stopped => 'Stopped',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::Completed,
                self::Stopped,
                self::Cancelled,
            ],
            true
        );
    }
}