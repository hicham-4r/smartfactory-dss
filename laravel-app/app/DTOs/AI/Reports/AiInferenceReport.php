<?php

namespace App\DTOs\AI\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class AiInferenceReport
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>|null  $metrics
     */
    public function __construct(
        public string $token,
        public string $operation,
        public CarbonImmutable $generatedAt,
        public string $generatedByName,
        public string $requestId,
        public array $context,
        public array $result,
        public ?array $metrics,
        public ?AiReportExplanation $explanation = null,
    ) {
        if (
            ($result['metadata']['data_classification'] ?? null)
            !== 'simulated_prototype'
        ) {
            throw new InvalidArgumentException(
                'The AI report must remain classified as simulated_prototype.',
            );
        }

        if (
            $this->explanation !== null
            && (
                ! $this->explanation->matchesOperation($this->operation)
                || ! hash_equals(
                    $this->requestId,
                    $this->explanation->inferenceRequestId,
                )
            )
        ) {
            throw new InvalidArgumentException(
                'The AI explanation is not linked to the exact report inference.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        $createdAt = CarbonImmutable::parse(
            (string) ($snapshot['created_at'] ?? ''),
        );
        $operation = (string) ($snapshot['operation'] ?? '');
        $requestId = (string) ($snapshot['request_id'] ?? '');
        $rawExplanation = $snapshot['explanation'] ?? null;
        $explanation = null;

        if ($rawExplanation !== null) {
            if (! is_array($rawExplanation)) {
                throw new InvalidArgumentException(
                    'The stored AI report explanation is invalid.',
                );
            }

            $explanation = AiReportExplanation::fromArray(
                payload: $rawExplanation,
                operation: $operation,
                reportRequestId: $requestId,
            );
        }

        return new self(
            token: (string) ($snapshot['token'] ?? ''),
            operation: $operation,
            generatedAt: $createdAt,
            generatedByName: (string) ($snapshot['generated_by_name'] ?? ''),
            requestId: $requestId,
            context: is_array($snapshot['context'] ?? null)
                ? $snapshot['context']
                : [],
            result: is_array($snapshot['result'] ?? null)
                ? $snapshot['result']
                : [],
            metrics: is_array($snapshot['metrics'] ?? null)
                ? $snapshot['metrics']
                : null,
            explanation: $explanation,
        );
    }

    public function title(): string
    {
        return match ($this->operation) {
            'production_forecast' => 'AI Production Forecast Report',
            'production_anomaly' => 'AI Production Anomaly Report',
            'maintenance_risk' => 'AI Maintenance-Risk Report',
            default => 'AI Inference Report',
        };
    }

    public function task(): string
    {
        return match ($this->operation) {
            'production_forecast' => 'production_forecasting',
            'production_anomaly' => 'production_anomaly',
            'maintenance_risk' => 'maintenance_risk',
            default => 'unknown',
        };
    }

    public function hasExplanation(): bool
    {
        return $this->explanation !== null;
    }
}
