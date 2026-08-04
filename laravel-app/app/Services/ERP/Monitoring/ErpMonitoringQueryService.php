<?php

namespace App\Services\ERP\Monitoring;

use App\Models\ErpSyncRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ErpMonitoringQueryService
{
    /**
     * @param array{
     *     source_system: string,
     *     status: string,
     *     trigger: string,
     *     per_page: int
     * } $filters
     */
    public function runs(
        array $filters
    ): LengthAwarePaginator {
        return ErpSyncRun::query()
            ->with([
                'initiatedBy:id,name,email',
            ])
            ->withCount([
                'resources',
                'failures',
            ])
            ->where(
                'source_system',
                $filters['source_system']
            )
            ->when(
                $filters['status'] !== 'all',

                fn ($query) =>
                    $query->where(
                        'status',
                        $filters['status']
                    )
            )
            ->when(
                $filters['trigger'] !== 'all',

                fn ($query) =>
                    $query->where(
                        'trigger',
                        $filters['trigger']
                    )
            )
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(
                $filters['per_page']
            )
            ->withQueryString();
    }

    public function run(
        ErpSyncRun $run
    ): ErpSyncRun {
        return $run->load([
            'initiatedBy:id,name,email',

            'resources' =>
                fn ($query) =>
                    $query->orderBy('id'),

            /*
             * Load only the stored sanitized failure fields used by
             * the view. safe_context is deliberately not selected.
             */
            'failures' =>
                fn ($query) =>
                    $query
                        ->select([
                            'id',
                            'erp_sync_run_id',
                            'erp_sync_run_resource_id',
                            'resource',
                            'stage',
                            'external_id',
                            'page',
                            'error_code',
                            'error_message',
                            'retryable',
                            'occurred_at',
                        ])
                        ->orderByDesc(
                            'occurred_at'
                        )
                        ->orderByDesc('id'),
        ]);
    }
}
