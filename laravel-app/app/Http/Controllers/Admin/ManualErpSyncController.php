<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunManualErpSyncRequest;
use App\Jobs\ERP\RunManualErpSyncJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class ManualErpSyncController extends Controller
{
    public function __invoke(
        RunManualErpSyncRequest $request
    ): RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user !== null,
            403
        );

        $options =
            $request
                ->synchronizationOptions();

        $cooldownSeconds = max(
            60,
            min(
                3600,
                (int) config(
                    'erp-manual-sync.cooldown_seconds',
                    300
                )
            )
        );

        $rateLimitKey =
            'erp-manual-sync:user:'
            .$user->getKey();

        $requestId = $this->requestId(
            $request
        );

        $accepted = RateLimiter::attempt(
            key:
                $rateLimitKey,

            maxAttempts:
                1,

            callback:
                function () use (
                    $user,
                    $requestId,
                    $options
                ): bool {
                    RunManualErpSyncJob::dispatch(
                        initiatedByUserId:
                            (int) $user->getKey(),

                        requestId:
                            $requestId,

                        perPage:
                            $options['per_page'],

                        maximumPagesPerResource:
                            $options['max_pages']
                    )->onQueue(
                        (string) config(
                            'erp-manual-sync.queue',
                            'erp-sync'
                        )
                    );

                    return true;
                },

            decaySeconds:
                $cooldownSeconds
        );

        if ($accepted === false) {
            return back()
                ->withErrors([
                    'manual_sync' =>
                        'A manual ERP synchronization was requested recently. Wait before submitting another request.',
                ]);
        }

        return redirect()
            ->route(
                'admin.erp-monitoring.index'
            )
            ->with(
                'erp_sync_status',
                'The incremental ERP synchronization was added to the secure queue.'
            );
    }

    private function requestId(
        RunManualErpSyncRequest $request
    ): string {
        $value = $request
            ->attributes
            ->get(
                'request_id'
            );

        if (
            is_string($value)
            && trim($value) !== ''
        ) {
            return substr(
                trim($value),
                0,
                64
            );
        }

        return (string) Str::uuid();
    }
}
