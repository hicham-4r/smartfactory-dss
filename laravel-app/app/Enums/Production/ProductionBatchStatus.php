<?php

namespace App\Enums\Production;

enum ProductionBatchStatus: string
{
    case Planned = 'planned';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [
                self::Ready,
                self::Cancelled,
            ],

            self::Ready => [
                self::InProgress,
                self::Blocked,
                self::Cancelled,
            ],

            self::InProgress => [
                self::Completed,
                self::Blocked,
                self::Cancelled,
            ],

            self::Blocked => [
                self::Ready,
                self::Cancelled,
            ],

            self::Completed,
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(
        self $target
    ): bool {
        return in_array(
            $target,
            $this->allowedTransitions(),
            true
        );
    }

    public function isTerminal(): bool
    {
        return in_array(
            $this,
            [
                self::Completed,
                self::Cancelled,
            ],
            true
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Ready => 'Ready',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Blocked => 'Blocked',
            self::Cancelled => 'Cancelled',
        };
    }
}