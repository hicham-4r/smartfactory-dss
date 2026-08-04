<?php

namespace App\DTOs\AI\Explanations;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class AiExplanationSnapshot
{
    /**
     * @param  array<string, mixed>  $inferencePayload
     * @param  array<string, mixed>  $inferenceData
     */
    public function __construct(
        public string $token,
        public int $userId,
        public string $sessionFingerprint,
        public string $operation,
        public string $inferenceRequestId,
        public array $inferencePayload,
        public array $inferenceData,
        public ?string $reportToken,
        public CarbonImmutable $expiresAt,
    ) {
        if (
            ! in_array(
                $operation,
                [
                    'production_forecast',
                    'production_anomaly',
                    'maintenance_risk',
                ],
                true,
            )
        ) {
            throw new InvalidArgumentException(
                'The explanation snapshot operation is unsupported.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user_id' => $this->userId,
            'session_fingerprint' => $this->sessionFingerprint,
            'operation' => $this->operation,
            'inference_request_id' => $this->inferenceRequestId,
            'inference_payload' => $this->inferencePayload,
            'inference_data' => $this->inferenceData,
            'report_token' => $this->reportToken,
            'expires_at' => $this->expiresAt->utc()->toIso8601String(),
        ];
    }
}
