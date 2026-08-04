<?php

namespace App\DTOs\AI\Inference;

use App\Enums\AI\AiInferenceStatus;

final readonly class AiInferenceResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public AiInferenceStatus $status,
        public string $operation,
        public string $requestId,
        public array $data = [],
        public ?string $message = null,
        public ?int $httpStatus = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(
        string $operation,
        string $requestId,
        array $data,
        int $httpStatus = 200,
    ): self {
        return new self(
            status: AiInferenceStatus::Success,
            operation: $operation,
            requestId: $requestId,
            data: $data,
            httpStatus: $httpStatus,
        );
    }

    public static function rejected(
        string $operation,
        string $requestId,
        string $message,
        ?int $httpStatus = null,
    ): self {
        return new self(
            status: AiInferenceStatus::Rejected,
            operation: $operation,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
        );
    }

    public static function unavailable(
        string $operation,
        string $requestId,
        string $message = 'The AI inference service is currently unavailable.',
        ?int $httpStatus = null,
    ): self {
        return new self(
            status: AiInferenceStatus::Unavailable,
            operation: $operation,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
        );
    }

    public static function notConfigured(
        string $operation,
        string $requestId,
        string $message = 'The AI inference service is not configured.',
    ): self {
        return new self(
            status: AiInferenceStatus::NotConfigured,
            operation: $operation,
            requestId: $requestId,
            message: $message,
        );
    }

    public static function invalidResponse(
        string $operation,
        string $requestId,
        string $message = 'The AI inference response did not match the expected contract.',
        ?int $httpStatus = null,
    ): self {
        return new self(
            status: AiInferenceStatus::InvalidResponse,
            operation: $operation,
            requestId: $requestId,
            message: $message,
            httpStatus: $httpStatus,
        );
    }

    public function succeeded(): bool
    {
        return $this->status === AiInferenceStatus::Success;
    }
}
