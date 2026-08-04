<?php

namespace App\Console\Commands;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Services\ERP\Sync\ErpSyncCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ErpSyncCommand extends Command
{
    protected $signature =
        'erp:sync
        {--resource=* : ERP resource to synchronize; may be repeated}
        {--all : Synchronize all supported ERP resources}
        {--from-start : Ignore the saved resume position for this run}
        {--per-page= : Number of ERP records requested per page}
        {--max-pages= : Maximum pages processed for each resource}';

    protected $description =
        'Synchronize Simulated Sage ERP records into SmartFactory DSS';

    public function handle(
        ErpSyncCoordinator $coordinator
    ): int {
        try {
            $resources =
                $this->resolveResources();

            if ($resources === null) {
                return self::INVALID;
            }

            $perPage =
                $this->positiveIntegerOption(
                    'per-page',
                    1,
                    200
                );

            if ($perPage === false) {
                return self::INVALID;
            }

            $maximumPages =
                $this->positiveIntegerOption(
                    'max-pages',
                    1,
                    100000
                );

            if ($maximumPages === false) {
                return self::INVALID;
            }

            $this->components->info(
                'Starting secure ERP synchronization'
            );

            $run = $coordinator
                ->synchronize(
                    resources: $resources,

                    trigger:
                        ErpSyncTrigger::Manual,

                    requestId:
                        (string) Str::uuid(),

                    perPage:
                        $perPage,

                    maximumPagesPerResource:
                        $maximumPages,

                    fromStart:
                        (bool) $this->option(
                            'from-start'
                        )
                );

            $this->table(
                [
                    'Property',
                    'Value',
                ],
                [
                    [
                        'Run UUID',
                        $run->run_uuid,
                    ],
                    [
                        'Source system',
                        $run->source_system,
                    ],
                    [
                        'Status',
                        $run->status->value,
                    ],
                    [
                        'Pages',
                        (string) $run
                            ->pages_processed,
                    ],
                    [
                        'Fetched',
                        (string) $run
                            ->records_fetched,
                    ],
                    [
                        'Created',
                        (string) $run
                            ->records_created,
                    ],
                    [
                        'Updated',
                        (string) $run
                            ->records_updated,
                    ],
                    [
                        'Skipped',
                        (string) $run
                            ->records_skipped,
                    ],
                    [
                        'Failed',
                        (string) $run
                            ->records_failed,
                    ],
                ]
            );

            $this->table(
                [
                    'Resource',
                    'Status',
                    'Pages',
                    'Fetched',
                    'Created',
                    'Updated',
                    'Skipped',
                    'Failed',
                ],
                $run->resources
                    ->map(
                        static fn ($resource): array => [
                            $resource
                                ->resource
                                ->value,

                            $resource
                                ->status
                                ->value,

                            (string) $resource
                                ->pages_processed,

                            (string) $resource
                                ->records_fetched,

                            (string) $resource
                                ->records_created,

                            (string) $resource
                                ->records_updated,

                            (string) $resource
                                ->records_skipped,

                            (string) $resource
                                ->records_failed,
                        ]
                    )
                    ->all()
            );

            return match ($run->status) {
                ErpSyncRunStatus::Completed => (
                    function (): int {
                        $this->components
                            ->success(
                                'ERP synchronization completed successfully.'
                            );

                        return self::SUCCESS;
                    }
                )(),

                ErpSyncRunStatus
                    ::CompletedWithErrors => (
                        function (): int {
                            $this->components
                                ->warn(
                                    'ERP synchronization completed with resource errors.'
                                );

                            return self::FAILURE;
                        }
                    )(),

                default => (
                    function () use ($run): int {
                        $this->components
                            ->error(
                                'ERP synchronization ended with status ['
                                .$run->status->value
                                .'].'
                            );

                        return self::FAILURE;
                    }
                )(),
            };
        } catch (Throwable $exception) {
            /*
             * Never display connector tokens, response bodies,
             * or complete ERP payloads.
             */
            $this->components->error(
                'ERP synchronization could not be started: '
                .$exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    /**
     * @return list<ErpResource>|null
     */
    private function resolveResources(): ?array
    {
        $all = (bool) $this->option('all');

        $requested =
            $this->option('resource');

        $requested = is_array($requested)
            ? $requested
            : [];

        if ($all && $requested !== []) {
            $this->components->error(
                'Use either --all or --resource, not both.'
            );

            return null;
        }

        if ($all) {
            return ErpResource::cases();
        }

        if ($requested === []) {
            $this->components->error(
                'Specify at least one --resource or use --all.'
            );

            return null;
        }

        $resources = [];

        foreach ($requested as $value) {
            $normalized = str_replace(
                '-',
                '_',
                strtolower(
                    trim((string) $value)
                )
            );

            $resource =
                ErpResource::tryFrom(
                    $normalized
                );

            if ($resource === null) {
                $this->components->error(
                    'Unknown ERP resource: '
                    .$value
                );

                return null;
            }

            $resources[
                $resource->value
            ] = $resource;
        }

        /*
         * Preserve ErpResource dependency order rather than the
         * order entered on the command line.
         */
        return array_values(
            array_filter(
                ErpResource::cases(),

                static fn (
                    ErpResource $resource
                ): bool =>
                    array_key_exists(
                        $resource->value,
                        $resources
                    )
            )
        );
    }

    private function positiveIntegerOption(
        string $name,
        int $minimum,
        int $maximum
    ): int|false|null {
        $value = $this->option($name);

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => $minimum,
                    'max_range' => $maximum,
                ],
            ]
        );

        if ($integer === false) {
            $this->components->error(
                '--'
                .$name
                .' must be between '
                .$minimum
                .' and '
                .$maximum
                .'.'
            );

            return false;
        }

        return $integer;
    }
}