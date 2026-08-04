<?php

namespace App\Services\AI\Inference;

use App\Contracts\AI\Inference\AiInferenceClientInterface;
use App\DTOs\AI\Inference\AiInferenceResult;

final readonly class DisabledAiInferenceClient implements AiInferenceClientInterface
{
    public function __construct(
        private string $message = 'The AI inference service is not configured.',
    ) {}

    public function models(string $requestId): AiInferenceResult
    {
        return AiInferenceResult::notConfigured(
            operation: 'models',
            requestId: $requestId,
            message: $this->message,
        );
    }

    public function forecast(
        array $payload,
        string $requestId,
    ): AiInferenceResult {
        return AiInferenceResult::notConfigured(
            operation: 'production_forecast',
            requestId: $requestId,
            message: $this->message,
        );
    }

    public function anomaly(
        array $payload,
        string $requestId,
    ): AiInferenceResult {
        return AiInferenceResult::notConfigured(
            operation: 'production_anomaly',
            requestId: $requestId,
            message: $this->message,
        );
    }

    public function maintenanceRisk(
        array $payload,
        string $requestId,
    ): AiInferenceResult {
        return AiInferenceResult::notConfigured(
            operation: 'maintenance_risk',
            requestId: $requestId,
            message: $this->message,
        );
    }
}
