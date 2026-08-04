<?php

namespace App\Services\AI;

use App\Contracts\AI\AiServiceClientInterface;
use App\DTOs\AI\AiServiceHealth;
use App\DTOs\AI\Analytics\AnalyticsContractValidationResult;
use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;

final readonly class DisabledAiServiceClient implements
    AiServiceClientInterface
{
    public function __construct(
        private string $message =
            'The FastAPI service is not configured.'
    ) {
    }

    public function health(
        string $requestId
    ): AiServiceHealth {
        return AiServiceHealth::notConfigured(
            $this->message
        );
    }

    public function validateAnalyticsContract(
        AnalyticsSnapshotContract $contract,
        string $requestId
    ): AnalyticsContractValidationResult {
        return AnalyticsContractValidationResult
            ::notConfigured(
                snapshotId:
                    $contract->snapshotId,
                message: $this->message
            );
    }
}
