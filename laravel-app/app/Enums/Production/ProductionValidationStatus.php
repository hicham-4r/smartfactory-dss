<?php

namespace App\Enums\Production;

enum ProductionValidationStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Validated,
                self::Rejected,
            ],

            /*
             * After an operator corrects and resubmits a rejected
             * record, its validation state returns to pending.
             */
            self::Rejected => [
                self::Pending,
            ],

            self::Validated => [],
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

    public function isFinal(): bool
    {
        return $this === self::Validated;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Validated => 'Validated',
            self::Rejected => 'Rejected',
        };
    }
}