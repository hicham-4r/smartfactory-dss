<?php

namespace App\Exceptions\Production;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class OptimisticLockException extends RuntimeException
{
    public static function stale(
        Model $model,
        int $expectedVersion
    ): self {
        return new self(
            sprintf(
                'The %s record [%s] was modified by another process. '
                .'Expected lock version [%d]. Refresh the record and retry.',
                class_basename($model),
                (string) $model->getKey(),
                $expectedVersion
            )
        );
    }
}