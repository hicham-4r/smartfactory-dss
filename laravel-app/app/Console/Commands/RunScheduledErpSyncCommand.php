<?php

namespace App\Console\Commands;

use App\Enums\ERP\ErpSyncGroup;
use App\Services\ERP\Sync\ArtisanErpGroupSyncRunner;
use App\Services\ERP\Sync\ErpSyncGroupRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunScheduledErpSyncCommand extends Command
{
    protected $signature = 'erp:sync:cycle
        {--per-page= : Number of records requested per ERP page}
        {--max-pages= : Maximum pages allowed for each resource}
        {--lock-seconds= : Maximum lifetime of the global cycle lock}
        {--continue-on-error : Continue with later groups after a failure}
        {--force : Run even when scheduled automation is disabled}';

    protected $description =
        'Run one secure incremental ERP synchronization cycle in dependency order.';

    public function handle(
        ErpSyncGroupRegistry $registry,
        ArtisanErpGroupSyncRunner $runner
    ): int {
        if (
            ! config(
                'erp-automation.enabled',
                false
            )
            && ! $this->option('force')
        ) {
            $this->components->warn(
                'ERP synchronization automation is disabled.'
            );

            return self::SUCCESS;
        }

        $perPage = $this->validatedInteger(
            option:
                'per-page',

            configuration:
                'erp-automation.per_page',

            minimum:
                1,

            maximum:
                100
        );

        $maxPages = $this->validatedInteger(
            option:
                'max-pages',

            configuration:
                'erp-automation.max_pages',

            minimum:
                1,

            maximum:
                10000
        );

        $lockSeconds = $this->validatedInteger(
            option:
                'lock-seconds',

            configuration:
                'erp-automation.lock_seconds',

            minimum:
                60,

            maximum:
                86400
        );

        if (
            $perPage === null
            || $maxPages === null
            || $lockSeconds === null
        ) {
            return self::INVALID;
        }

        $lockKey = trim(
            (string) config(
                'erp-automation.lock_key',
                'smartfactory:erp:incremental-sync-cycle'
            )
        );

        if ($lockKey === '') {
            $this->components->error(
                'The ERP synchronization lock key is invalid.'
            );

            return self::INVALID;
        }

        try {
            $lock = Cache::lock(
                $lockKey,
                $lockSeconds
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->components->error(
                'The ERP synchronization lock could not be created.'
            );

            return self::FAILURE;
        }

        if (! $lock->get()) {
            $this->components->warn(
                'Another ERP synchronization cycle is already running. This execution was skipped safely.'
            );

            Log::notice(
                'Scheduled ERP synchronization skipped because the global lock is held.',
                [
                    'lock_key' =>
                        $lockKey,
                ]
            );

            return self::SUCCESS;
        }

        try {
            return $this->executeCycle(
                registry:
                    $registry,

                runner:
                    $runner,

                perPage:
                    $perPage,

                maxPages:
                    $maxPages
            );
        } catch (Throwable $exception) {
            report($exception);

            Log::error(
                'Scheduled ERP synchronization terminated unexpectedly.',
                [
                    'exception_class' =>
                        $exception::class,

                    /*
                     * Do not log configuration values, tokens, payloads,
                     * response bodies, or external credentials.
                     */
                    'message' =>
                        $exception->getMessage(),
                ]
            );

            $this->components->error(
                'The ERP synchronization cycle terminated unexpectedly.'
            );

            return self::FAILURE;
        } finally {
            $this->releaseLock(
                $lock
            );
        }
    }

    private function executeCycle(
        ErpSyncGroupRegistry $registry,
        ArtisanErpGroupSyncRunner $runner,
        int $perPage,
        int $maxPages
    ): int {
        $startedAt = microtime(true);
        $continueOnError =
            (bool) $this->option(
                'continue-on-error'
            );

        $results = [];
        $failedGroups = [];

        $this->components->info(
            'Starting incremental ERP synchronization cycle.'
        );

        foreach (
            $registry->groups()
            as $group
        ) {
            $groupName =
                $group->inputName();

            $this->newLine();

            $this->components->info(
                "Synchronizing ERP group [{$groupName}]."
            );

            $groupStartedAt =
                microtime(true);

            $exitCode = $runner->run(
                group:
                    $group,

                perPage:
                    $perPage,

                maxPages:
                    $maxPages
            );

            $output =
                $runner->output();

            if ($output !== '') {
                $this->line(
                    $output
                );
            }

            $durationSeconds = round(
                microtime(true)
                - $groupStartedAt,
                2
            );

            $successful =
                $exitCode === self::SUCCESS;

            $results[] = [
                $groupName,
                $successful
                    ? 'completed'
                    : 'failed',
                $exitCode,
                $durationSeconds.' s',
            ];

            if ($successful) {
                Log::info(
                    'Scheduled ERP group synchronization completed.',
                    [
                        'group' =>
                            $groupName,

                        'duration_seconds' =>
                            $durationSeconds,
                    ]
                );

                continue;
            }

            $failedGroups[] =
                $groupName;

            Log::error(
                'Scheduled ERP group synchronization failed.',
                [
                    'group' =>
                        $groupName,

                    'exit_code' =>
                        $exitCode,

                    'duration_seconds' =>
                        $durationSeconds,
                ]
            );

            if (! $continueOnError) {
                $this->components->error(
                    "The synchronization cycle stopped after group [{$groupName}] failed."
                );

                break;
            }
        }

        $this->newLine();

        $this->table(
            [
                'Group',
                'Status',
                'Exit code',
                'Duration',
            ],
            $results
        );

        $cycleDuration = round(
            microtime(true)
            - $startedAt,
            2
        );

        if ($failedGroups !== []) {
            $this->components->error(
                'ERP synchronization cycle completed with failed groups: '
                .implode(
                    ', ',
                    $failedGroups
                )
                .'.'
            );

            Log::error(
                'Scheduled ERP synchronization cycle completed with errors.',
                [
                    'failed_groups' =>
                        $failedGroups,

                    'duration_seconds' =>
                        $cycleDuration,
                ]
            );

            return self::FAILURE;
        }

        $this->components->info(
            "ERP synchronization cycle completed successfully in {$cycleDuration} seconds."
        );

        Log::info(
            'Scheduled ERP synchronization cycle completed successfully.',
            [
                'group_count' =>
                    count($results),

                'duration_seconds' =>
                    $cycleDuration,
            ]
        );

        return self::SUCCESS;
    }

    private function validatedInteger(
        string $option,
        string $configuration,
        int $minimum,
        int $maximum
    ): ?int {
        $optionValue =
            $this->option(
                $option
            );

        $value =
            $optionValue !== null
            && $optionValue !== ''
                ? $optionValue
                : config(
                    $configuration
                );

        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' =>
                        $minimum,

                    'max_range' =>
                        $maximum,
                ],
            ]
        );

        if ($validated === false) {
            $this->components->error(
                "The --{$option} value must be an integer between {$minimum} and {$maximum}."
            );

            return null;
        }

        return (int) $validated;
    }

    private function releaseLock(
        Lock $lock
    ): void {
        try {
            $lock->release();
        } catch (Throwable $exception) {
            report($exception);

            Log::warning(
                'The global ERP synchronization lock could not be released normally.',
                [
                    'exception_class' =>
                        $exception::class,
                ]
            );
        }
    }
}