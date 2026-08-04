<?php

namespace App\Jobs\ERP;

use App\Services\ERP\Sync\ManualErpSynchronizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunManualErpSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 20;

    public int $timeout = 7200;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $initiatedByUserId,
        public readonly string $requestId,
        public readonly int $perPage,
        public readonly int $maximumPagesPerResource
    ) {
    }

    public function handle(
        ManualErpSynchronizationService $synchronization
    ): void {
        $lockKey = trim(
            (string) config(
                'erp-manual-sync.lock_key',
                'smartfactory:erp:incremental-sync-cycle'
            )
        );

        $lockSeconds = max(
            60,
            min(
                86400,
                (int) config(
                    'erp-manual-sync.lock_seconds',
                    7200
                )
            )
        );

        $retryAfterSeconds = max(
            10,
            min(
                600,
                (int) config(
                    'erp-manual-sync.retry_after_seconds',
                    60
                )
            )
        );

        $lock = Cache::lock(
            $lockKey,
            $lockSeconds
        );

        if (! $lock->get()) {
            Log::notice(
                'Manual ERP synchronization job delayed because another synchronization cycle owns the global lock.',
                [
                    'request_id' =>
                        $this->requestId,

                    'initiated_by_user_id' =>
                        $this->initiatedByUserId,
                ]
            );

            $this->release(
                $retryAfterSeconds
            );

            return;
        }

        try {
            $runIds =
                $synchronization
                    ->synchronizeAll(
                        initiatedByUserId:
                            $this->initiatedByUserId,

                        requestId:
                            $this->requestId,

                        perPage:
                            $this->perPage,

                        maximumPagesPerResource:
                            $this->maximumPagesPerResource
                    );

            Log::info(
                'Manual ERP synchronization completed.',
                [
                    'request_id' =>
                        $this->requestId,

                    'initiated_by_user_id' =>
                        $this->initiatedByUserId,

                    'run_ids' =>
                        $runIds,
                ]
            );
        } finally {
            $lock->release();
        }
    }

    public function failed(
        Throwable $exception
    ): void {
        Log::error(
            'Manual ERP synchronization job failed.',
            [
                'request_id' =>
                    $this->requestId,

                'initiated_by_user_id' =>
                    $this->initiatedByUserId,

                'exception_class' =>
                    $exception::class,

                /*
                 * Do not log connector configuration, credentials,
                 * authorization headers, complete payloads or cursors.
                 */
                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
}
