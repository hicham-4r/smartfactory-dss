<?php

namespace App\Services\AI;

use App\Contracts\AI\AiServiceClientInterface;
use App\DTOs\AI\Analytics\AnalyticsContractValidationResult;
use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;
use Illuminate\Support\Str;
use Throwable;

final readonly class AnalyticsContractService
{
    public function __construct(
        private AiServiceClientInterface $client
    ) {
    }

    public function validate(
        AnalyticsSnapshotContract $contract,
        ?string $requestId = null
    ): AnalyticsContractValidationResult {
        $requestId = $this->safeRequestId(
            $requestId
        );

        try {
            return $this->client
                ->validateAnalyticsContract(
                    contract: $contract,
                    requestId: $requestId
                );
        } catch (Throwable) {
            return AnalyticsContractValidationResult
                ::unavailable(
                    snapshotId:
                        $contract->snapshotId,
                    requestId: $requestId,
                    message:
                        'The FastAPI analytics contract validation failed safely.'
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
