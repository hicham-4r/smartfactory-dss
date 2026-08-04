<?php

namespace App\DTOs\Dashboard;

use JsonSerializable;

final readonly class ProductionSupervisorEventItem implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $eventNumber,
        public string $title,
        public string $eventType,
        public string $severity,
        public string $productionLineName,
        public ?string $machineName,
        public ?string $shiftName,
        public string $startedAt,
        public ?int $durationMinutes,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event_number' => $this->eventNumber,
            'title' => $this->title,
            'event_type' => $this->eventType,
            'severity' => $this->severity,
            'production_line_name' => $this->productionLineName,
            'machine_name' => $this->machineName,
            'shift_name' => $this->shiftName,
            'started_at' => $this->startedAt,
            'duration_minutes' => $this->durationMinutes,
        ];
    }

    /** @return array<string, int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
