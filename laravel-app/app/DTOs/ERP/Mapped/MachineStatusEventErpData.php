<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpMachineStatus;
use Carbon\CarbonImmutable;

final readonly class MachineStatusEventErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $machineExternalId,
        public ErpMachineStatus $status,
        public CarbonImmutable $occurredAt,
        public ?CarbonImmutable $endedAt,
        public ?string $reason,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'machine_external_id' =>
                $this->machineExternalId,

            'status' =>
                $this->status->value,

            'occurred_at' =>
                $this->occurredAt
                    ->utc()
                    ->toIso8601String(),

            'ended_at' =>
                $this->endedAt
                    ?->utc()
                    ->toIso8601String(),

            /*
             * Persist a source-derived duration so analytics remain portable
             * between MySQL and SQLite without database-specific date SQL.
             */
            'duration_minutes' =>
                $this->durationMinutes(),

            'reason' =>
                $this->reason,
        ]);
    }

    private function durationMinutes(): ?int
    {
        if ($this->endedAt === null) {
            return null;
        }

        return max(
            0,
            (int) round(
                $this->occurredAt
                    ->diffInMinutes(
                        $this->endedAt
                    )
            )
        );
    }
}
