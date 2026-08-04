<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;

final class ErpRateLimitException extends ErpConnectorException
{
    private function __construct(
        ?ErpResource $resource,
        private readonly ?int $retryAfterSeconds
    ) {
        parent::__construct(
            message:
                'The ERP server rate limit was reached.',

            retryable: true,
            resource: $resource,

            safeContext: [
                'retry_after_seconds' =>
                    $retryAfterSeconds,
            ]
        );
    }

    public static function forResource(
        ?ErpResource $resource = null,
        ?int $retryAfterSeconds = null
    ): self {
        return new self(
            $resource,
            $retryAfterSeconds
        );
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}