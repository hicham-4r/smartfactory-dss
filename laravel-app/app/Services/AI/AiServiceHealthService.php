<?php

namespace App\Services\AI;

use App\Contracts\AI\AiServiceClientInterface;
use App\DTOs\AI\AiServiceHealth;
use Illuminate\Support\Str;
use Throwable;

final readonly class AiServiceHealthService
{
    public function __construct(
        private AiServiceClientInterface $client
    ) {
    }

    public function snapshot(
        ?string $requestId = null
    ): AiServiceHealth {
        $requestId = $this->safeRequestId(
            $requestId
        );

        try {
            return $this->client
                ->health($requestId);
        } catch (Throwable) {
            return AiServiceHealth::unavailable(
                requestId: $requestId,
                message:
                    'The FastAPI health check failed safely.'
            );
        }
    }

    private function safeRequestId(
        ?string $requestId
    ): string {
        if (
            is_string($requestId)
            && preg_match(
                '/^[A-Za-z0-9._:-]{1,100}$/',
                $requestId
            ) === 1
        ) {
            return $requestId;
        }

        return (string) Str::uuid();
    }
}
