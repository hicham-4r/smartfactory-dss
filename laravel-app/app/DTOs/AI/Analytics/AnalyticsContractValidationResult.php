<?php

namespace App\DTOs\AI\Analytics;

use App\Enums\AI\AnalyticsContractValidationStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class AnalyticsContractValidationResult implements
    JsonSerializable
{
    /**
     * @param list<string> $acceptedSections
     */
    public function __construct(
        public AnalyticsContractValidationStatus $status,
        public CarbonImmutable $checkedAt,
        public string $snapshotId,
        public string $contractName =
            AnalyticsSnapshotContract::CONTRACT_NAME,
        public string $contractVersion =
            AnalyticsSnapshotContract::CONTRACT_VERSION,
        public array $acceptedSections = [],
        public ?string $requestId = null,
        public ?string $message = null,
    ) {
        foreach (
            $acceptedSections
            as $section
        ) {
            if (
                ! is_string($section)
                || ! in_array(
                    $section,
                    [
                        'production_kpis',
                        'production_breakdowns',
                        'maintenance_kpis',
                        'quality_kpis',
                    ],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'The analytics contract response contains an unsupported section.'
                );
            }
        }

        if (
            count($acceptedSections)
            !== count(
                array_unique(
                    $acceptedSections
                )
            )
        ) {
            throw new InvalidArgumentException(
                'The analytics contract response contains duplicate sections.'
            );
        }
    }

    public static function notConfigured(
        string $snapshotId,
        string $message =
            'The FastAPI analytics contract is not configured.'
    ): self {
        return new self(
            status:
                AnalyticsContractValidationStatus
                    ::NotConfigured,
            checkedAt: CarbonImmutable::now(),
            snapshotId: $snapshotId,
            message: $message,
        );
    }

    public static function unavailable(
        string $snapshotId,
        ?string $requestId = null,
        string $message =
            'The FastAPI analytics contract is unavailable.'
    ): self {
        return new self(
            status:
                AnalyticsContractValidationStatus
                    ::Unavailable,
            checkedAt: CarbonImmutable::now(),
            snapshotId: $snapshotId,
            requestId: $requestId,
            message: $message,
        );
    }

    public static function rejected(
        string $snapshotId,
        ?string $requestId = null,
        string $message =
            'The analytics snapshot did not satisfy the expected contract.'
    ): self {
        return new self(
            status:
                AnalyticsContractValidationStatus
                    ::Rejected,
            checkedAt: CarbonImmutable::now(),
            snapshotId: $snapshotId,
            requestId: $requestId,
            message: $message,
        );
    }

    public function isAccepted(): bool
    {
        return $this->status->isAccepted();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'checked_at' =>
                $this->checkedAt
                    ->utc()
                    ->toIso8601String(),
            'snapshot_id' => $this->snapshotId,
            'contract_name' => $this->contractName,
            'contract_version' =>
                $this->contractVersion,
            'accepted_sections' =>
                $this->acceptedSections,
            'request_id' => $this->requestId,
            'message' => $this->message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
