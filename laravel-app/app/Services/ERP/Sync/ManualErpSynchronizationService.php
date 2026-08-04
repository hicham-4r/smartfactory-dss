<?php

namespace App\Services\ERP\Sync;

use App\Contracts\ERP\ErpConnectorInterface;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Models\ErpSyncRun;
use RuntimeException;

class ManualErpSynchronizationService
{
    public function __construct(
        private readonly ErpConnectorInterface $connector,
        private readonly ErpSyncCoordinator $coordinator,
        private readonly ErpSyncGroupRegistry $groups
    ) {
    }

    /**
     * Run every external ERP dependency group in the registry's safe
     * order and preserve the initiating administrator on every run.
     *
     * @return list<int>
     */
    public function synchronizeAll(
        int $initiatedByUserId,
        string $requestId,
        int $perPage,
        int $maximumPagesPerResource
    ): array {
        $runIds = [];

        foreach ($this->groups->groups() as $group) {
            $missingPrerequisites =
                $this->groups
                    ->missingPrerequisiteResources(
                        sourceSystem:
                            $this->connector
                                ->sourceSystem(),

                        group:
                            $group
                    );

            if ($missingPrerequisites !== []) {
                throw new RuntimeException(
                    'Manual ERP synchronization cannot continue because required prerequisite resources are missing.'
                );
            }

            $run = $this->coordinator
                ->synchronize(
                    resources:
                        $this->groups
                            ->resources(
                                $group
                            ),

                    trigger:
                        ErpSyncTrigger::Manual,

                    initiatedByUserId:
                        $initiatedByUserId,

                    requestId:
                        $requestId,

                    perPage:
                        $perPage,

                    maximumPagesPerResource:
                        $maximumPagesPerResource,

                    /*
                     * The administrator interface performs incremental
                     * synchronization only. Full replay remains a
                     * controlled CLI maintenance operation.
                     */
                    fromStart:
                        false
                );

            $runIds[] =
                (int) $run->getKey();

            if (
                $run->status
                !== ErpSyncRunStatus::Completed
            ) {
                throw new RuntimeException(
                    'A manual ERP dependency group did not complete successfully.'
                );
            }
        }

        return $runIds;
    }
}
