<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\Production\ProductionEventSeverity;
use Carbon\CarbonImmutable;

final readonly class DowntimeEventErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $eventNumber,
        public string $machineExternalId,
        public string $productionLineExternalId,
        public ?string $batchExternalId,
        public ?string $shiftExternalId,
        public ?string $operatorExternalId,
        public ProductionEventSeverity $severity,
        public string $category,
        public string $downtimeType,
        public ?string $reasonCode,
        public ?string $reason,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $endedAt,
        public ?int $durationMinutes,
        public bool $isResolved,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'event_number' =>
                $this->eventNumber,

            /*
             * The DSS stores imported downtime inside the unified
             * production_events table.
             */
            'event_type' =>
                'downtime',

            'title' =>
                $this->title(),

            'description' =>
                $this->description(),

            'machine_external_id' =>
                $this->machineExternalId,

            'production_line_external_id' =>
                $this->productionLineExternalId,

            'batch_external_id' =>
                $this->batchExternalId,

            'shift_external_id' =>
                $this->shiftExternalId,

            'operator_external_id' =>
                $this->operatorExternalId,

            'severity' =>
                $this->severity->value,

            /*
             * Preserve source semantics in the mapped contract. These
             * fields are ignored when the target table does not expose
             * dedicated columns.
             */
            'category' =>
                $this->category,

            'downtime_type' =>
                $this->downtimeType,

            'reason_code' =>
                $this->reasonCode,

            'reason' =>
                $this->reason,

            'started_at' =>
                $this->startedAt
                    ->utc()
                    ->toIso8601String(),

            'ended_at' =>
                $this->endedAt
                    ?->utc()
                    ->toIso8601String(),

            'duration_minutes' =>
                $this->durationMinutes,

            'is_resolved' =>
                $this->isResolved,

            'resolved_at' =>
                $this->isResolved
                    ? $this->endedAt
                        ?->utc()
                        ->toIso8601String()
                    : null,
        ]);
    }

    private function title(): string
    {
        $type = trim(
            str_replace(
                [
                    '_',
                    '-',
                ],
                ' ',
                $this->downtimeType
            )
        );

        $type = $type === ''
            ? 'Downtime'
            : ucwords(strtolower($type));

        return mb_substr(
            'Downtime - '.$type,
            0,
            180
        );
    }

    private function description(): ?string
    {
        $parts = [];

        if (
            $this->reasonCode !== null
            && trim($this->reasonCode) !== ''
        ) {
            $parts[] =
                '['.trim($this->reasonCode).']';
        }

        if (
            $this->reason !== null
            && trim($this->reason) !== ''
        ) {
            $parts[] =
                trim($this->reason);
        }

        if ($parts === []) {
            return 'Imported '
                .$this->category
                .' downtime event.';
        }

        return implode(
            ' ',
            $parts
        );
    }
}
