<?php

namespace App\Contracts\AI\Inference;

use App\DTOs\AI\Inference\AiInferenceResult;

interface AiInferenceClientInterface
{
    public function models(string $requestId): AiInferenceResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function forecast(
        array $payload,
        string $requestId,
    ): AiInferenceResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function anomaly(
        array $payload,
        string $requestId,
    ): AiInferenceResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function maintenanceRisk(
        array $payload,
        string $requestId,
    ): AiInferenceResult;
}
