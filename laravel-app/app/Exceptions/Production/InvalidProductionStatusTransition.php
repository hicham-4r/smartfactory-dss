<?php

namespace App\Exceptions\Production;

use BackedEnum;
use DomainException;

final class InvalidProductionStatusTransition extends DomainException
{
    public static function between(
        string $resource,
        BackedEnum $current,
        BackedEnum $target
    ): self {
        return new self(
            sprintf(
                'Invalid %s status transition from [%s] to [%s].',
                $resource,
                (string) $current->value,
                (string) $target->value
            )
        );
    }
}