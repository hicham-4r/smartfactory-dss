<?php

namespace App\Enums\Production;

enum ProductionOrderStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Released = 'released';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::Planned,
                self::Cancelled,
            ],

            self::Planned => [
                self::Released,
                self::Cancelled,
            ],

            self::Released => [
                self::InProgress,
                self::Cancelled,
            ],

            self::InProgress => [
                self::Completed,
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
            self::Draft => 'Draft',
            self::Planned => 'Planned',
            self::Released => 'Released',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}