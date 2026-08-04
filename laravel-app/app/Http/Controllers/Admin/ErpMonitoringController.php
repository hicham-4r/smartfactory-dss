<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrowseErpMonitoringRequest;
use App\Models\ErpSyncRun;
use App\Services\ERP\Monitoring\ErpMonitoringQueryService;
use App\Services\ERP\Monitoring\ErpSyncHealthService;
use Illuminate\Http\Response;

final class ErpMonitoringController extends Controller
{
    public function __construct(
        private readonly ErpSyncHealthService $health,
        private readonly ErpMonitoringQueryService $queries
    ) {
    }

    public function index(
        BrowseErpMonitoringRequest $request
    ): Response {
        $filters = $request->filters();

        $staleAfterMinutes = max(
            1,
            min(
                10080,
                (int) config(
                    'erp-monitoring.stale_after_minutes',
                    45
                )
            )
        );

        return $this->noStoreView(
            'admin.erp-monitoring.index',
            [
                'health' =>
                    $this->health->snapshot(
                        $filters['source_system'],
                        $staleAfterMinutes
                    ),

                'runs' =>
                    $this->queries->runs(
                        $filters
                    ),

                'filters' =>
                    $filters,
            ]
        );
    }

    public function show(
        ErpSyncRun $erpSyncRun
    ): Response {
        return $this->noStoreView(
            'admin.erp-monitoring.show',
            [
                'run' =>
                    $this->queries->run(
                        $erpSyncRun
                    ),
            ]
        );
    }

    /**
     * Prevent authenticated monitoring information from being cached.
     *
     * @param array<string, mixed> $data
     */
    private function noStoreView(
        string $view,
        array $data
    ): Response {
        return response()
            ->view(
                $view,
                $data
            )
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header(
                'Pragma',
                'no-cache'
            )
            ->header(
                'Expires',
                '0'
            );
    }
}
