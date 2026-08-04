<?php

namespace App\DTOs\ERP\Mapped;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\Mapped\Concerns\SerializesErpMappedEntity;
use App\Enums\ERP\ErpMaintenanceStatus;
use App\Enums\ERP\ErpMaintenanceType;
use Carbon\CarbonImmutable;

final readonly class MaintenanceHistoryErpData implements
    ErpMappedEntityInterface
{
    use SerializesErpMappedEntity;

    public function __construct(
        public ErpSourceRecord $source,
        public string $maintenanceNumber,
        public string $machineExternalId,
        public ErpMaintenanceType $maintenanceType,
        public ErpMaintenanceStatus $status,
        public ?CarbonImmutable $scheduledAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
        public ?string $performedByExternalId,
        public ?string $description,
        public ?string $actionsTaken,
        public int $downtimeMinutes,
        public ?string $cost,
        public ?string $currency,
    ) {
    }

    public function toArray(): array
    {
        return $this->envelope([
            'maintenance_number' =>
                $this->maintenanceNumber,

            'machine_external_id' =>
                $this->machineExternalId,

            'maintenance_type' =>
                $this->maintenanceType->value,

            'status' =>
                $this->status->value,

            'scheduled_at' =>
                $this->scheduledAt
                    ?->utc()
                    ->toIso8601String(),

            'started_at' =>
                $this->startedAt
                    ?->utc()
                    ->toIso8601String(),

            'completed_at' =>
                $this->completedAt
                    ?->utc()
                    ->toIso8601String(),

            'performed_by_external_id' =>
                $this->performedByExternalId,

            'description' =>
                $this->description,

            'actions_taken' =>
                $this->actionsTaken,

            'downtime_minutes' =>
                $this->downtimeMinutes,

            'cost' =>
                $this->cost,

            'currency' =>
                $this->currency,
        ]);
    }
}