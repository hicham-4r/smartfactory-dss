<?php

namespace App\Services\ERP\Sync;

use App\Enums\ERP\ErpSyncGroup;
use Illuminate\Contracts\Console\Kernel;

class ArtisanErpGroupSyncRunner
{
    public function __construct(
        private readonly Kernel $kernel
    ) {
    }

    public function run(
        ErpSyncGroup $group,
        int $perPage,
        int $maxPages
    ): int {
        /*
         * Do not pass --from-start here.
         *
         * Scheduled executions must continue from the successful
         * incremental checkpoints created by previous synchronization
         * runs.
         */
        return $this->kernel->call(
            'erp:sync:validate',
            [
                'group' =>
                    $group->inputName(),

                '--per-page' =>
                    $perPage,

                '--max-pages' =>
                    $maxPages,
            ]
        );
    }

    public function output(): string
    {
        return trim(
            $this->kernel->output()
        );
    }
}