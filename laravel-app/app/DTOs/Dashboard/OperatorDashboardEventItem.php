<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class OperatorDashboardEventItem implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $eventNumber,
        public string $title,
        public string $eventType,
        public string $severity,
        public string $productionLineName,
        public ?string $machineName,
        public string $startedAt,
        public ?int $durationMinutes,
        public bool $isResolved,
    ) {
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_number' => $this->eventNumber,
            'title' => $this->title,
            'event_type' => $this->eventType,
            'severity' => $this->severity,
            'production_line_name' =>
                $this->productionLineName,
            'machine_name' => $this->machineName,
            'started_at' => $this->startedAt,
            'duration_minutes' =>
                $this->durationMinutes,
            'is_resolved' => $this->isResolved,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
