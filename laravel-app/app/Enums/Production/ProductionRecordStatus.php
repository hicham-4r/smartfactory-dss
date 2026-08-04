<?php

namespace App\Enums\Production;

enum ProductionRecordStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Locked = 'locked';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::Submitted,
            ],

            /*
             * A rejected submitted record may return to draft
             * for controlled correction.
             */
            self::Submitted => [
                self::Draft,
                self::Locked,
            ],

            self::Locked => [],
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

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Locked => 'Locked',
        };
    }
}