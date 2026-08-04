<?php

namespace App\Console\Commands;

use App\DTOs\ERP\Monitoring\ErpSyncHealthSnapshot;
use App\Services\ERP\Monitoring\ErpSyncHealthService;
use Illuminate\Console\Command;
use JsonException;

final class ShowErpSyncHealthCommand extends Command
{
    protected $signature = 'erp:sync:health
        {--source= : ERP source system to inspect}
        {--stale-after= : Minutes before a successful checkpoint is stale}
        {--details : Display every resource checkpoint and recent failure}
        {--json : Output machine-readable JSON}
        {--fail-on-degraded : Return failure when health is degraded}';

    protected $description =
        'Display a safe ERP synchronization health report';

    public function handle(
        ErpSyncHealthService $health
    ): int {
        $sourceSystem =
            $this->sourceSystem();

        if ($sourceSystem === null) {
            return self::INVALID;
        }

        $staleAfterMinutes =
            $this->staleAfterMinutes();

        if ($staleAfterMinutes === null) {
            return self::INVALID;
        }

        /*
         * ErpSyncHealthService exposes build(), not snapshot().
         */
        $snapshot = $health->snapshot(
            $sourceSystem,
            $staleAfterMinutes
        );

        if ((bool) $this->option('json')) {
            return $this->renderJson(
                $snapshot
            );
        }

        $this->renderHumanReadable(
            $snapshot
        );

        return $this->exitCode(
            $snapshot
        );
    }

    private function renderHumanReadable(
        ErpSyncHealthSnapshot $snapshot
    ): void {
        match ($snapshot->status) {
            'healthy' =>
                $this->components->success(
                    'ERP synchronization health is healthy.'
                ),

            'degraded' =>
                $this->components->warn(
                    'ERP synchronization health is degraded.'
                ),

            default =>
                $this->components->error(
                    'ERP synchronization health is unhealthy.'
                ),
        };

        $latestRun =
            $snapshot->latestRun;

        $this->table(
            [
                'Property',
                'Value',
            ],
            [
                [
                    'Status',
                    strtoupper(
                        $snapshot->status
                    ),
                ],
                [
                    'Source system',
                    $snapshot->sourceSystem,
                ],
                [
                    'Generated at',
                    $snapshot
                        ->generatedAt
                        ->utc()
                        ->toIso8601String(),
                ],
                [
                    'Stale after',
                    $snapshot
                        ->staleAfterMinutes
                    .' minutes',
                ],
                [
                    'Latest run UUID',
                    $latestRun[
                        'run_uuid'
                    ] ?? 'none',
                ],
                [
                    'Latest run status',
                    $latestRun[
                        'status'
                    ] ?? 'none',
                ],
                [
                    'Last successful run',
                    $snapshot->summary[
                        'last_successful_run_at'
                    ] ?? 'never',
                ],
                [
                    'Minutes since success',
                    $snapshot->summary[
                        'minutes_since_success'
                    ] ?? 'unknown',
                ],
                [
                    'Resource states',
                    (
                        $snapshot->summary[
                            'registered_states'
                        ] ?? 0
                    )
                    .'/'
                    .(
                        $snapshot->summary[
                            'expected_resources'
                        ] ?? 0
                    ),
                ],
                [
                    'Stale states',
                    $snapshot->summary[
                        'stale_states'
                    ] ?? 0,
                ],
                [
                    'Active locks',
                    $snapshot->summary[
                        'locked_states'
                    ] ?? 0,
                ],
                [
                    'Stale locks',
                    $snapshot->summary[
                        'stale_locks'
                    ] ?? 0,
                ],
                [
                    'Runs in window',
                    $snapshot->summary[
                        'runs_in_window'
                    ] ?? 0,
                ],
                [
                    'Failed runs in window',
                    $snapshot->summary[
                        'failed_runs_in_window'
                    ] ?? 0,
                ],
                [
                    'Failures in window',
                    $snapshot->summary[
                        'failures_in_window'
                    ] ?? 0,
                ],
            ]
        );

        if ($snapshot->reasons !== []) {
            $this->newLine();

            $this->components->warn(
                'Health findings'
            );

            foreach (
                $snapshot->reasons
                as $reason
            ) {
                $this->line(
                    ' - '.$reason
                );
            }
        }

        if (
            ! (bool) $this->option(
                'details'
            )
        ) {
            return;
        }

        $this->renderResources(
            $snapshot
        );

        $this->renderFailures(
            $snapshot
        );
    }

    private function renderResources(
        ErpSyncHealthSnapshot $snapshot
    ): void {
        $this->newLine();

        $rows = array_map(
            static function (
                array $resource
            ): array {
                return [
                    $resource[
                        'resource'
                    ] ?? '',

                    $resource[
                        'freshness'
                    ] ?? '',

                    $resource[
                        'last_successful_sync_at'
                    ] ?? 'never',

                    $resource[
                        'minutes_since_success'
                    ] ?? '',

                    $resource[
                        'consecutive_failures'
                    ] ?? 0,

                    (
                        $resource[
                            'locked'
                        ] ?? false
                    )
                        ? 'yes'
                        : 'no',

                    (
                        $resource[
                            'stale_lock'
                        ] ?? false
                    )
                        ? 'yes'
                        : 'no',

                    $resource[
                        'last_error_code'
                    ] ?? '',
                ];
            },
            $snapshot->resources
        );

        $this->table(
            [
                'Resource',
                'Freshness',
                'Last success',
                'Age (min)',
                'Failures',
                'Locked',
                'Stale lock',
                'Last error',
            ],
            $rows
        );
    }

    private function renderFailures(
        ErpSyncHealthSnapshot $snapshot
    ): void {
        if ($snapshot->failures === []) {
            return;
        }

        $this->newLine();

        $this->components->warn(
            'Recent sanitized ERP failures'
        );

        $rows = array_map(
            static function (
                array $failure
            ): array {
                return [
                    $failure[
                        'occurred_at'
                    ] ?? '',

                    $failure[
                        'resource'
                    ] ?? '',

                    $failure[
                        'stage'
                    ] ?? '',

                    $failure[
                        'external_id'
                    ] ?? '',

                    $failure[
                        'page'
                    ] ?? '',

                    $failure[
                        'error_code'
                    ] ?? '',

                    (
                        $failure[
                            'retryable'
                        ] ?? false
                    )
                        ? 'yes'
                        : 'no',

                    $failure[
                        'error_message'
                    ] ?? '',
                ];
            },
            $snapshot->failures
        );

        $this->table(
            [
                'Occurred at',
                'Resource',
                'Stage',
                'External ID',
                'Page',
                'Error code',
                'Retryable',
                'Message',
            ],
            $rows
        );
    }

    private function renderJson(
        ErpSyncHealthSnapshot $snapshot
    ): int {
        try {
            $json = json_encode(
                $snapshot,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
            );

            $this->line(
                $json
            );
        } catch (JsonException) {
            $this->components->error(
                'The ERP health report could not be encoded as JSON.'
            );

            return self::FAILURE;
        }

        return $this->exitCode(
            $snapshot
        );
    }

    private function exitCode(
        ErpSyncHealthSnapshot $snapshot
    ): int {
        if ($snapshot->isUnhealthy()) {
            return self::FAILURE;
        }

        if (
            $snapshot->isDegraded()
            && (bool) $this->option(
                'fail-on-degraded'
            )
        ) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function sourceSystem(): ?string
    {
        $value =
            $this->option(
                'source'
            );

        if (
            $value === null
            || $value === ''
        ) {
            $value = config(
                'erp-monitoring.source_system',
                'simulated_sage'
            );
        }

        if (! is_string($value)) {
            $this->components->error(
                'The ERP source system must be a string.'
            );

            return null;
        }

        $value = strtolower(
            trim($value)
        );

        if (
            $value === ''
            || strlen($value) > 50
            || preg_match(
                '/^[a-z0-9][a-z0-9_-]*$/',
                $value
            ) !== 1
        ) {
            $this->components->error(
                'The ERP source system is invalid.'
            );

            return null;
        }

        return $value;
    }

    private function staleAfterMinutes(): ?int
    {
        $value =
            $this->option(
                'stale-after'
            );

        if (
            $value === null
            || $value === ''
        ) {
            $value = config(
                'erp-monitoring.stale_after_minutes',
                45
            );
        }

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 10080,
                ],
            ]
        );

        if ($validated === false) {
            $this->components->error(
                '--stale-after must be an integer between 1 and 10080 minutes.'
            );

            return null;
        }

        return (int) $validated;
    }
}