<?php

namespace App\Services\ERP\Monitoring;

use App\DTOs\ERP\Monitoring\ErpSyncHealthSnapshot;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Models\ErpSyncFailure;
use App\Models\ErpSyncRun;
use App\Models\ErpSyncState;
use App\Services\ERP\Sync\ErpSyncGroupRegistry;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;
use UnitEnum;

class ErpSyncHealthService
{
    public function __construct(
        private readonly ErpSyncGroupRegistry $groups
    ) {
    }

    public function snapshot(
        string $sourceSystem,
        int $staleAfterMinutes
    ): ErpSyncHealthSnapshot {
        $sourceSystem = $this->normalizeSourceSystem(
            $sourceSystem
        );

        $staleAfterMinutes = max(
            1,
            min(
                10080,
                $staleAfterMinutes
            )
        );

        $now = CarbonImmutable::now();

        $staleBoundary = $now->subMinutes(
            $staleAfterMinutes
        );

        $windowHours = max(
            1,
            min(
                720,
                (int) config(
                    'erp-monitoring.window_hours',
                    24
                )
            )
        );

        $windowStart = $now->subHours(
            $windowHours
        );

        $failureLimit = max(
            1,
            min(
                100,
                (int) config(
                    'erp-monitoring.recent_failure_limit',
                    20
                )
            )
        );

        $leaseTtlSeconds = max(
            30,
            min(
                3600,
                (int) config(
                    'erp-sync.lease_ttl_seconds',
                    300
                )
            )
        );

        /*
         * Only resources returned by the dependency-group registry are
         * external ERP resources.
         *
         * Local DSS telemetry such as run_logs is deliberately excluded
         * from health calculations and failure reporting.
         *
         * @var list<string> $expectedResourceValues
         */
        $expectedResourceValues = array_values(
            array_unique(
                array_map(
                    static function (
                        mixed $resource
                    ): string {
                        if ($resource instanceof BackedEnum) {
                            return (string) $resource->value;
                        }

                        if ($resource instanceof UnitEnum) {
                            return $resource->name;
                        }

                        return (string) $resource;
                    },
                    $this->groups->allResources()
                )
            )
        );

        $expectedResourceCount = count(
            $expectedResourceValues
        );

        $latestRun = ErpSyncRun::query()
            ->where(
                'source_system',
                $sourceSystem
            )
            ->with([
                'resources',
                'failures',
            ])
            ->latest('id')
            ->first();

        $latestCompletedRun = ErpSyncRun::query()
            ->where(
                'source_system',
                $sourceSystem
            )
            ->where(
                'status',
                ErpSyncRunStatus::Completed->value
            )
            ->whereNotNull(
                'finished_at'
            )
            ->latest(
                'finished_at'
            )
            ->first();

        /*
         * Filter checkpoints to the 16 externally synchronized ERP
         * resources. Old local run_logs checkpoints remain untouched.
         */
        $states = ErpSyncState::query()
            ->where(
                'source_system',
                $sourceSystem
            )
            ->whereIn(
                'resource',
                $expectedResourceValues
            )
            ->orderBy(
                'resource'
            )
            ->get();

        $resources = $states
            ->map(
                function (
                    ErpSyncState $state
                ) use (
                    $now,
                    $staleBoundary,
                    $leaseTtlSeconds
                ): array {
                    $lastSuccess = $this->date(
                        $state->last_successful_sync_at
                    );

                    $lockAcquiredAt = $this->date(
                        $state->lock_acquired_at
                    );

                    $locked =
                        is_string(
                            $state->lock_owner
                        )
                        && trim(
                            $state->lock_owner
                        ) !== '';

                    $staleLock =
                        $locked
                        && $lockAcquiredAt !== null
                        && $lockAcquiredAt
                            ->addSeconds(
                                $leaseTtlSeconds
                            )
                            ->lessThan(
                                $now
                            );

                    $freshness = match (true) {
                        $lastSuccess === null =>
                            'never',

                        $lastSuccess->lessThan(
                            $staleBoundary
                        ) =>
                            'stale',

                        default =>
                            'fresh',
                    };

                    return [
                        'resource' =>
                            $this->enumValue(
                                $state->resource
                            ),

                        'freshness' =>
                            $freshness,

                        'last_successful_sync_at' =>
                            $this->iso(
                                $lastSuccess
                            ),

                        'minutes_since_success' =>
                            $lastSuccess === null
                                ? null
                                : max(
                                    0,
                                    $lastSuccess
                                        ->diffInMinutes(
                                            $now
                                        )
                                ),

                        'last_source_updated_at' =>
                            $this->iso(
                                $this->date(
                                    $state
                                        ->last_source_updated_at
                                )
                            ),

                        'last_source_version' =>
                            $state
                                ->last_source_version,

                        'resume_page' =>
                            (int) (
                                $state->resume_page
                                ?? 1
                            ),

                        'consecutive_failures' =>
                            (int) (
                                $state
                                    ->consecutive_failures
                                ?? 0
                            ),

                        'last_error_code' =>
                            $state
                                ->last_error_code,

                        'locked' =>
                            $locked,

                        'lock_acquired_at' =>
                            $this->iso(
                                $lockAcquiredAt
                            ),

                        'stale_lock' =>
                            $staleLock,
                    ];
                }
            )
            ->values()
            ->all();

        $registeredStateCount = count(
            $resources
        );

        $missingStates = max(
            0,
            $expectedResourceCount
                - $registeredStateCount
        );

        $staleStates = count(
            array_filter(
                $resources,
                static fn (
                    array $resource
                ): bool =>
                    $resource['freshness']
                    !== 'fresh'
            )
        );

        $lockedStates = count(
            array_filter(
                $resources,
                static fn (
                    array $resource
                ): bool =>
                    (bool) $resource['locked']
            )
        );

        $staleLocks = count(
            array_filter(
                $resources,
                static fn (
                    array $resource
                ): bool =>
                    (bool) $resource['stale_lock']
            )
        );

        $failingStates = count(
            array_filter(
                $resources,
                static fn (
                    array $resource
                ): bool =>
                    (
                        $resource[
                            'consecutive_failures'
                        ]
                        ?? 0
                    ) > 0
            )
        );

        $runsInWindow = ErpSyncRun::query()
            ->where(
                'source_system',
                $sourceSystem
            )
            ->where(
                'started_at',
                '>=',
                $windowStart
            )
            ->count();

        $failedRunsInWindow = ErpSyncRun::query()
            ->where(
                'source_system',
                $sourceSystem
            )
            ->where(
                'started_at',
                '>=',
                $windowStart
            )
            ->whereIn(
                'status',
                [
                    ErpSyncRunStatus::Failed
                        ->value,

                    ErpSyncRunStatus
                        ::CompletedWithErrors
                        ->value,
                ]
            )
            ->count();

        /*
         * Restrict the failure list to external ERP resources.
         * Failures for local run_logs telemetry are not ERP connector
         * health failures and therefore are excluded.
         */
        $recentFailures = ErpSyncFailure::query()
            ->whereIn(
                'erp_sync_run_id',
                ErpSyncRun::query()
                    ->select('id')
                    ->where(
                        'source_system',
                        $sourceSystem
                    )
            )
            ->whereIn(
                'resource',
                $expectedResourceValues
            )
            ->where(
                'occurred_at',
                '>=',
                $windowStart
            )
            ->latest(
                'occurred_at'
            )
            ->limit(
                $failureLimit
            )
            ->get()
            ->map(
                fn (
                    ErpSyncFailure $failure
                ): array => [
                    'resource' =>
                        $this->enumValue(
                            $failure->resource
                        ),

                    'stage' =>
                        $this->enumValue(
                            $failure->stage
                        ),

                    'external_id' =>
                        $failure->external_id,

                    'page' =>
                        $failure->page,

                    'error_code' =>
                        $failure->error_code,

                    /*
                     * The stored message has already been sanitized.
                     * safe_context is deliberately not exposed.
                     */
                    'error_message' =>
                        $failure->error_message,

                    'retryable' =>
                        (bool) $failure
                            ->retryable,

                    'occurred_at' =>
                        $this->iso(
                            $this->date(
                                $failure
                                    ->occurred_at
                            )
                        ),
                ]
            )
            ->values()
            ->all();

        [
            $status,
            $reasons,
        ] = $this->evaluateHealth(
            latestRun:
                $latestRun,

            latestCompletedRun:
                $latestCompletedRun,

            now:
                $now,

            staleBoundary:
                $staleBoundary,

            leaseTtlSeconds:
                $leaseTtlSeconds,

            missingStates:
                $missingStates,

            staleStates:
                $staleStates,

            staleLocks:
                $staleLocks,

            failingStates:
                $failingStates
        );

        $lastSuccessfulAt = $this->date(
            $latestCompletedRun
                ?->finished_at
        );

        return ErpSyncHealthSnapshot::fromArray([
            'status' =>
                $status,

            'generated_at' =>
                $now
                    ->utc()
                    ->toIso8601String(),

            'source_system' =>
                $sourceSystem,

            'stale_after_minutes' =>
                $staleAfterMinutes,

            'latest_run' =>
                $this->runRow(
                    $latestRun
                ),

            'summary' => [
                'expected_resources' =>
                    $expectedResourceCount,

                'registered_states' =>
                    $registeredStateCount,

                'missing_states' =>
                    $missingStates,

                'fresh_states' =>
                    max(
                        0,
                        $registeredStateCount
                            - $staleStates
                    ),

                'stale_states' =>
                    $staleStates,

                'locked_states' =>
                    $lockedStates,

                'stale_locks' =>
                    $staleLocks,

                'states_with_failures' =>
                    $failingStates,

                'window_hours' =>
                    $windowHours,

                'runs_in_window' =>
                    $runsInWindow,

                'failed_runs_in_window' =>
                    $failedRunsInWindow,

                'failures_in_window' =>
                    count(
                        $recentFailures
                    ),

                'last_successful_run_at' =>
                    $this->iso(
                        $lastSuccessfulAt
                    ),

                'minutes_since_success' =>
                    $lastSuccessfulAt === null
                        ? null
                        : max(
                            0,
                            $lastSuccessfulAt
                                ->diffInMinutes(
                                    $now
                                )
                        ),
            ],

            'resources' =>
                $resources,

            'recent_failures' =>
                $recentFailures,

            'reasons' =>
                $reasons,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runRow(
        ?ErpSyncRun $run
    ): ?array {
        if ($run === null) {
            return null;
        }

        $startedAt = $this->date(
            $run->started_at
        );

        $finishedAt = $this->date(
            $run->finished_at
        );

        return [
            'run_uuid' =>
                $run->run_uuid,

            'trigger' =>
                $this->enumValue(
                    $run->trigger
                ),

            'status' =>
                $this->enumValue(
                    $run->status
                ),

            'started_at' =>
                $this->iso(
                    $startedAt
                ),

            'finished_at' =>
                $this->iso(
                    $finishedAt
                ),

            'duration_seconds' =>
                $startedAt !== null
                && $finishedAt !== null
                    ? max(
                        0,
                        $startedAt
                            ->diffInSeconds(
                                $finishedAt
                            )
                    )
                    : null,

            'pages_processed' =>
                (int) $run
                    ->pages_processed,

            'records_fetched' =>
                (int) $run
                    ->records_fetched,

            'records_created' =>
                (int) $run
                    ->records_created,

            'records_updated' =>
                (int) $run
                    ->records_updated,

            'records_skipped' =>
                (int) $run
                    ->records_skipped,

            'records_failed' =>
                (int) $run
                    ->records_failed,

            'failure_count' =>
                $run->failures
                    ->count(),
        ];
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function evaluateHealth(
        ?ErpSyncRun $latestRun,
        ?ErpSyncRun $latestCompletedRun,
        CarbonImmutable $now,
        CarbonImmutable $staleBoundary,
        int $leaseTtlSeconds,
        int $missingStates,
        int $staleStates,
        int $staleLocks,
        int $failingStates
    ): array {
        $critical = [];
        $warnings = [];

        if ($latestRun === null) {
            $critical[] =
                'No ERP synchronization run has been recorded.';
        } else {
            $latestStatus = $this->enumValue(
                $latestRun->status
            );

            if (
                in_array(
                    $latestStatus,
                    [
                        ErpSyncRunStatus::Failed
                            ->value,

                        ErpSyncRunStatus
                            ::CompletedWithErrors
                            ->value,
                    ],
                    true
                )
            ) {
                $critical[] =
                    'The latest ERP synchronization run failed.';
            }

            if ($latestStatus === 'running') {
                $startedAt = $this->date(
                    $latestRun->started_at
                );

                if (
                    $startedAt !== null
                    && $startedAt
                        ->addSeconds(
                            $leaseTtlSeconds * 2
                        )
                        ->lessThan(
                            $now
                        )
                ) {
                    $critical[] =
                        'The latest ERP synchronization run appears stuck.';
                } else {
                    $warnings[] =
                        'An ERP synchronization run is currently in progress.';
                }
            }
        }

        if ($latestCompletedRun === null) {
            $critical[] =
                'No successful ERP synchronization run has been recorded.';
        } else {
            $finishedAt = $this->date(
                $latestCompletedRun
                    ->finished_at
            );

            if (
                $finishedAt === null
                || $finishedAt
                    ->lessThan(
                        $staleBoundary
                    )
            ) {
                $warnings[] =
                    'The latest successful ERP synchronization run is stale.';
            }
        }

        if ($missingStates > 0) {
            $critical[] =
                $missingStates
                .' ERP resource state(s) are missing.';
        }

        if ($staleLocks > 0) {
            $critical[] =
                $staleLocks
                .' stale ERP resource lock(s) were detected.';
        }

        if ($staleStates > 0) {
            $warnings[] =
                $staleStates
                .' ERP checkpoint(s) are stale or have never completed.';
        }

        if ($failingStates > 0) {
            $warnings[] =
                $failingStates
                .' ERP checkpoint(s) report consecutive failures.';
        }

        $critical = array_values(
            array_unique(
                $critical
            )
        );

        $warnings = array_values(
            array_unique(
                $warnings
            )
        );

        if ($critical !== []) {
            return [
                'unhealthy',
                [
                    ...$critical,
                    ...$warnings,
                ],
            ];
        }

        if ($warnings !== []) {
            return [
                'degraded',
                $warnings,
            ];
        }

        return [
            'healthy',
            [],
        ];
    }

    private function normalizeSourceSystem(
        string $value
    ): string {
        $value = strtolower(
            trim(
                $value
            )
        );

        if (
            $value === ''
            || strlen($value) > 50
            || preg_match(
                '/^[a-z0-9][a-z0-9_-]*$/',
                $value
            ) !== 1
        ) {
            return 'simulated_sage';
        }

        return $value;
    }

    private function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return (string) (
            $value
            ?? ''
        );
    }

    private function date(
        mixed $value
    ): ?CarbonImmutable {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance(
                $value
            );
        }

        if (
            is_string($value)
            && trim($value) !== ''
        ) {
            try {
                return CarbonImmutable::parse(
                    $value
                );
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function iso(
        ?CarbonImmutable $value
    ): ?string {
        return $value
            ?->utc()
            ->toIso8601String();
    }
}