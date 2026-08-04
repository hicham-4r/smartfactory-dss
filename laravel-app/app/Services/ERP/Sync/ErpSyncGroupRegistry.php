<?php

namespace App\Services\ERP\Sync;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncGroup;
use Illuminate\Support\Facades\DB;

final class ErpSyncGroupRegistry
{
    /**
     * @return list<ErpSyncGroup>
     */
    public function groups(): array
    {
        return ErpSyncGroup::cases();
    }

    /**
     * Resources exposed by the external Sage simulator.
     *
     * RunLogs intentionally remains outside these groups because it
     * represents local DSS telemetry and has no external ERP endpoint.
     *
     * @return list<ErpResource>
     */
    public function resources(
        ErpSyncGroup $group
    ): array {
        return match ($group) {
            ErpSyncGroup::Catalog => [
                ErpResource::ProductFamilies,
                ErpResource::Products,
            ],

            ErpSyncGroup::FactoryMaster => [
                ErpResource::ProductionLines,
                ErpResource::Machines,
                ErpResource::Shifts,
                ErpResource::Operators,
                ErpResource::OperatorAssignments,
            ],

            ErpSyncGroup::ProductionExecution => [
                ErpResource::WorkOrders,
                ErpResource::Batches,
                ErpResource::MachineRuns,
            ],

            ErpSyncGroup::Maintenance => [
                ErpResource::MachineStatusEvents,
                ErpResource::DowntimeEvents,
                ErpResource::MaintenanceHistory,
            ],

            ErpSyncGroup::Quality => [
                ErpResource::FinishedLots,
                ErpResource::Inspections,
                ErpResource::Nonconformities,
            ],
        };
    }

    /**
     * All resources synchronized through dependency groups.
     *
     * @return list<ErpResource>
     */
    public function allResources(): array
    {
        $resources = [];

        foreach ($this->groups() as $group) {
            foreach ($this->resources($group) as $resource) {
                $resources[$resource->value] =
                    $resource;
            }
        }

        return array_values($resources);
    }

    /**
     * Resources managed locally rather than imported from Sage.
     *
     * @return list<ErpResource>
     */
    public function localOnlyResources(): array
    {
        return [
            ErpResource::RunLogs,
        ];
    }

    /**
     * @return list<ErpSyncGroup>
     */
    public function prerequisites(
        ErpSyncGroup $group
    ): array {
        return match ($group) {
            ErpSyncGroup::Catalog,
            ErpSyncGroup::FactoryMaster => [],

            ErpSyncGroup::ProductionExecution => [
                ErpSyncGroup::Catalog,
                ErpSyncGroup::FactoryMaster,
            ],

            ErpSyncGroup::Maintenance => [
                ErpSyncGroup::FactoryMaster,
                ErpSyncGroup::ProductionExecution,
            ],

            ErpSyncGroup::Quality => [
                ErpSyncGroup::Catalog,
                ErpSyncGroup::FactoryMaster,
                ErpSyncGroup::ProductionExecution,
            ],
        };
    }

    /**
     * @return list<ErpResource>
     */
    public function prerequisiteResources(
        ErpSyncGroup $group
    ): array {
        $resources = [];

        foreach (
            $this->prerequisites($group)
            as $prerequisite
        ) {
            foreach (
                $this->resources($prerequisite)
                as $resource
            ) {
                $resources[$resource->value] =
                    $resource;
            }
        }

        return array_values($resources);
    }

    /**
     * @return list<ErpResource>
     */
    public function missingPrerequisiteResources(
        string $sourceSystem,
        ErpSyncGroup $group
    ): array {
        $required =
            $this->prerequisiteResources(
                $group
            );

        if ($required === []) {
            return [];
        }

        $successful = DB::table(
            'erp_sync_states'
        )
            ->where(
                'source_system',
                $sourceSystem
            )
            ->whereNotNull(
                'last_successful_sync_at'
            )
            ->pluck('resource')
            ->map(
                static fn (mixed $value): string =>
                    (string) $value
            )
            ->all();

        return array_values(
            array_filter(
                $required,
                static fn (
                    ErpResource $resource
                ): bool =>
                    ! in_array(
                        $resource->value,
                        $successful,
                        true
                    )
            )
        );
    }

    public function fromInput(
        string $value
    ): ?ErpSyncGroup {
        $normalized = strtolower(
            trim($value)
        );

        $normalized = str_replace(
            '-',
            '_',
            $normalized
        );

        return ErpSyncGroup::tryFrom(
            $normalized
        );
    }
}