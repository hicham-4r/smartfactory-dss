<?php

namespace App\DTOs\Analytics;

use JsonSerializable;

final readonly class QualityCategoryMetric implements JsonSerializable
{
    public function __construct(
        public string $category,
        public int $nonconformityCount,
        public int $openCount,
        public int $resolvedCount,
        public int $minorCount,
        public int $majorCount,
        public int $criticalCount,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'nonconformity_count' => $this->nonconformityCount,
            'open_count' => $this->openCount,
            'resolved_count' => $this->resolvedCount,
            'minor_count' => $this->minorCount,
            'major_count' => $this->majorCount,
            'critical_count' => $this->criticalCount,
        ];
    }

    /** @return array<string, int|string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
