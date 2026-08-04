<?php

namespace App\DTOs\AI\Explanations;

use App\Enums\AI\AiExplanationStatus;

final readonly class AiExplanationResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public AiExplanationStatus $status,
        public string $requestId,
        public array $data = [],
        public ?string $message = null,
        public ?int $httpStatus = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(
        string $requestId,
        array $data,
        int $httpStatus = 200,
    ): self {
        return new self(
            status: AiExplanationStatus::Success,
            requestId: $requestId,
            data: $data,
            httpStatus: $httpStatus,
        );
    }

    public static function rejected(
        string $requestId,
        string $message =
            'The verified facts could not be accepted for explanation.',
        ?int $httpStatus = null,
    ): self {
        return new self(
            status: AiExplanationStatus::Rejected,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
        );
    }

    public static function unavailable(
        string $requestId,
        string $message =
            'The guarded explanation service is currently unavailable.',
        ?int $httpStatus = null,
    ): self {
        return new self(
            status: AiExplanationStatus::Unavailable,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
        );
    }

    public static function invalidResponse(
        string $requestId,
        string $message =
            'The explanation response did not satisfy the expected contract.',
        ?int $httpStatus = null,
    ): self {
        return new self(
            status: AiExplanationStatus::InvalidResponse,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
        );
    }

    public static function rateLimited(
        string $requestId,
        int $retryAfterSeconds,
        string $message =
            'Explanation generation is temporarily rate limited.',
        int $httpStatus = 429,
    ): self {
        return new self(
            status: AiExplanationStatus::RateLimited,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
            retryAfterSeconds: max(1, $retryAfterSeconds),
        );
    }

    public static function notConfigured(
        string $requestId,
        string $message =
            'The guarded explanation service is not configured.',
    ): self {
        return new self(
            status: AiExplanationStatus::NotConfigured,
            requestId: $requestId,
            message: $message,
        );
    }

    public function succeeded(): bool
    {
        return $this->status->succeeded();
    }
}
