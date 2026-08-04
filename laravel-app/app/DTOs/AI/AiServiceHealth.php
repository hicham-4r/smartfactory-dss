<?php

namespace App\DTOs\AI;

use App\Enums\AI\AiServiceHealthStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class AiServiceHealth implements JsonSerializable
{
    public function __construct(
        public AiServiceHealthStatus $status,
        public CarbonImmutable $checkedAt,
        public ?int $latencyMilliseconds = null,
        public ?string $serviceVersion = null,
        public ?string $apiVersion = null,
        public ?string $requestId = null,
        public ?string $message = null,
    ) {
        if (
            $latencyMilliseconds !== null
            && $latencyMilliseconds < 0
        ) {
            throw new InvalidArgumentException(
                'AI service latency cannot be negative.'
            );
        }
    }

    public static function notConfigured(
        string $message =
            'The FastAPI service is not configured.'
    ): self {
        return new self(
            status: AiServiceHealthStatus::NotConfigured,
            checkedAt: CarbonImmutable::now(),
            message: $message,
        );
    }

    public static function unavailable(
        ?string $requestId = null,
        string $message =
            'The FastAPI service is unavailable.'
    ): self {
        return new self(
            status: AiServiceHealthStatus::Unavailable,
            checkedAt: CarbonImmutable::now(),
            requestId: $requestId,
            message: $message,
        );
    }

    public function isOperational(): bool
    {
        return $this->status->isOperational();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'checked_at' => $this->checkedAt
                ->utc()
                ->toIso8601String(),
            'latency_ms' => $this->latencyMilliseconds,
            'service_version' => $this->serviceVersion,
            'api_version' => $this->apiVersion,
            'request_id' => $this->requestId,
            'message' => $this->message,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
