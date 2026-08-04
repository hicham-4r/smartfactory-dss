<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;
use Throwable;

final class ErpTransportException extends ErpConnectorException
{
    /**
     * @param array<string, mixed> $safeContext
     */
    public static function unreachable(
        ?ErpResource $resource = null,
        array $safeContext = [],
        ?Throwable $previous = null
    ): self {
        return new self(
            message:
                'The ERP server could not be reached.',

            retryable: true,
            resource: $resource,
            safeContext: $safeContext,
            previous: $previous
        );
    }
}