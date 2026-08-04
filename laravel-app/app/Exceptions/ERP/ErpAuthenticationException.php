<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;

final class ErpAuthenticationException extends ErpConnectorException
{
    public static function rejected(
        ?ErpResource $resource = null,
        int $statusCode = 401
    ): self {
        return new self(
            message:
                'The ERP server rejected connector authentication.',

            retryable: false,
            resource: $resource,

            safeContext: [
                'status_code' => $statusCode,
            ]
        );
    }
}