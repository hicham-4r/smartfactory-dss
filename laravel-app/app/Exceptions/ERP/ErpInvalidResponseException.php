<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;

final class ErpInvalidResponseException extends ErpConnectorException
{
    /**
     * @param array<string, mixed> $safeContext
     */
    public static function forResource(
        ErpResource $resource,
        string $reason,
        array $safeContext = []
    ): self {
        return new self(
            message:
                'The ERP server returned an invalid response: '
                .$reason,

            retryable: false,
            resource: $resource,
            safeContext: $safeContext
        );
    }
}