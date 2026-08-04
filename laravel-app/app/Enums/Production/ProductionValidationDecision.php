<?php

namespace App\Enums\Production;

enum ProductionValidationDecision: string
{
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function validationStatus(): ProductionValidationStatus
    {
        return match ($this) {
            self::Validated =>
                ProductionValidationStatus::Validated,

            self::Rejected =>
                ProductionValidationStatus::Rejected,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'Validated',
            self::Rejected => 'Rejected',
        };
    }
}