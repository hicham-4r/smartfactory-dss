<?php

namespace App\Exceptions\AI;

use RuntimeException;

final class InferenceFeaturePreparationException extends RuntimeException
{
    public static function invalidSelection(string $message): self
    {
        return new self($message);
    }

    public static function insufficientHistory(string $message): self
    {
        return new self($message);
    }
}
