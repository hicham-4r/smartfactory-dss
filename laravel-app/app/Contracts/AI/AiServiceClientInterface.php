<?php

namespace App\Contracts\AI;

use App\DTOs\AI\AiServiceHealth;
use App\DTOs\AI\Analytics\AnalyticsContractValidationResult;
use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;

interface AiServiceClientInterface
{
    public function health(
        string $requestId
    ): AiServiceHealth;

    public function validateAnalyticsContract(
        AnalyticsSnapshotContract $contract,
        string $requestId
    ): AnalyticsContractValidationResult;
}
