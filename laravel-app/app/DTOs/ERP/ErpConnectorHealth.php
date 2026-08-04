<?php

namespace App\DTOs\ERP;

use App\Enums\ERP\ErpConnectorHealthStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ErpConnectorHealth
{
    public ?string $message;

    public function __construct(
        public ErpConnectorHealthStatus $status,
        public CarbonImmutable $checkedAt,
        public ?int $latencyMilliseconds = null,
        ?string $message = null,
    ) {
        if (
            $latencyMilliseconds !== null
            && $latencyMilliseconds < 0
        ) {
            throw new InvalidArgumentException(
                'ERP connector latency cannot be negative.'
            );
        }

        $message = $message === null
            ? null
            : trim($message);

        if ($message === '') {
            $message = null;
        }

        if (
            $message !== null
            && mb_strlen($message) > 500
        ) {
            throw new InvalidArgumentException(
                'ERP connector health message may not exceed 500 characters.'
            );
        }

        $this->message = $message;
    }

    public function isAvailable(): bool
    {
        return $this->status->isAvailable();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,

            'checked_at' =>
                $this->checkedAt
                    ->utc()
                    ->toIso8601String(),

            'latency_ms' =>
                $this->latencyMilliseconds,

            'message' => $this->message,
        ];
    }
}