<?php

namespace App\DTOs\ERP\Monitoring;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use JsonSerializable;

final readonly class ErpSyncHealthSnapshot implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $latestRun
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $resources
     * @param list<array<string, mixed>> $failures
     * @param list<string> $reasons
     */
    public function __construct(
        public string $status,
        public CarbonImmutable $generatedAt,
        public string $sourceSystem,
        public int $staleAfterMinutes,
        public ?array $latestRun,
        public array $summary,
        public array $resources,
        public array $failures,
        public array $reasons
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $generatedAt =
            $data['generated_at']
            ?? CarbonImmutable::now();

        if ($generatedAt instanceof CarbonInterface) {
            $generatedAt =
                CarbonImmutable::instance(
                    $generatedAt
                );
        } else {
            $generatedAt =
                CarbonImmutable::parse(
                    (string) $generatedAt
                );
        }

        $latestRun =
            isset($data['latest_run'])
            && is_array($data['latest_run'])
                ? $data['latest_run']
                : null;

        $summary =
            isset($data['summary'])
            && is_array($data['summary'])
                ? $data['summary']
                : [];

        $resources = self::arrayList(
            $data['resources']
            ?? []
        );

        $failures = self::arrayList(
            $data['recent_failures']
            ?? $data['failures']
            ?? []
        );

        $reasons = array_values(
            array_filter(
                array_map(
                    static function (
                        mixed $reason
                    ): string {
                        return is_scalar($reason)
                            ? trim((string) $reason)
                            : '';
                    },
                    is_array(
                        $data['reasons']
                        ?? null
                    )
                        ? $data['reasons']
                        : []
                ),
                static fn (
                    string $reason
                ): bool => $reason !== ''
            )
        );

        return new self(
            status:
                (string) (
                    $data['status']
                    ?? 'unhealthy'
                ),

            generatedAt:
                $generatedAt,

            sourceSystem:
                (string) (
                    $data['source_system']
                    ?? 'simulated_sage'
                ),

            staleAfterMinutes:
                (int) (
                    $data[
                        'stale_after_minutes'
                    ]
                    ?? 45
                ),

            latestRun:
                $latestRun,

            summary:
                $summary,

            resources:
                $resources,

            failures:
                $failures,

            reasons:
                $reasons
        );
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function isDegraded(): bool
    {
        return $this->status === 'degraded';
    }

    public function isUnhealthy(): bool
    {
        return $this->status === 'unhealthy';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' =>
                $this->status,

            'generated_at' =>
                $this->generatedAt
                    ->utc()
                    ->toIso8601String(),

            'source_system' =>
                $this->sourceSystem,

            'stale_after_minutes' =>
                $this->staleAfterMinutes,

            'latest_run' =>
                $this->latestRun,

            'summary' =>
                $this->summary,

            'resources' =>
                $this->resources,

            'recent_failures' =>
                $this->failures,

            'reasons' =>
                $this->reasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function arrayList(
        mixed $value
    ): array {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                $value,
                static fn (
                    mixed $item
                ): bool => is_array($item)
            )
        );
    }
}