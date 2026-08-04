<?php

namespace App\Console\Commands;

use App\Contracts\ERP\ErpConnectorInterface;
use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncGroup;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Exceptions\ERP\ErpPersistenceException;
use App\Models\ErpSyncRun;
use App\Services\ERP\Sync\ErpSyncCoordinator;
use App\Services\ERP\Sync\ErpSyncGroupRegistry;
use App\Services\ERP\Sync\ErpSyncTargetRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ErpSyncValidateCommand extends Command
{
    protected $signature =
        'erp:sync:validate
        {group? : Group: catalog, factory-master, production-execution, maintenance or quality}
        {--all : Run every dependency group in safe order}
        {--list : Display the available groups}
        {--from-start : Read the selected resources from page one}
        {--per-page= : ERP records requested per page}
        {--max-pages= : Maximum pages processed for each resource}';

    protected $description =
        'Synchronize and validate ERP resources in dependency-safe groups';

    public function handle(
        ErpSyncGroupRegistry $groups,
        ErpSyncTargetRegistry $targets,
        ErpConnectorInterface $connector,
        ErpSyncCoordinator $coordinator
    ): int {
        if ((bool) $this->option('list')) {
            $this->displayGroupCatalog(
                $groups
            );

            return self::SUCCESS;
        }

        $requestedGroups =
            $this->resolveGroups($groups);

        if ($requestedGroups === null) {
            return self::INVALID;
        }

        $perPage =
            $this->positiveIntegerOption(
                name: 'per-page',
                minimum: 1,
                maximum: 200
            );

        if ($perPage === false) {
            return self::INVALID;
        }

        $maximumPages =
            $this->positiveIntegerOption(
                name: 'max-pages',
                minimum: 1,
                maximum: 100000
            );

        if ($maximumPages === false) {
            return self::INVALID;
        }

        foreach (
            $requestedGroups
            as $group
        ) {
            $this->newLine();

            $this->components->info(
                'Validating ERP group: '
                .$group->label()
            );

            $issues = $this->preflightIssues(
                group: $group,
                groups: $groups,
                targets: $targets,
                connector: $connector
            );

            if ($issues !== []) {
                $this->components->error(
                    'ERP group preflight failed.'
                );

                foreach ($issues as $issue) {
                    $this->line(
                        ' - '.$issue
                    );
                }

                return self::FAILURE;
            }

            $missingPrerequisites =
                $groups
                    ->missingPrerequisiteResources(
                        sourceSystem:
                            $connector
                                ->sourceSystem(),

                        group: $group
                    );

            if (
                $missingPrerequisites !== []
            ) {
                $this->components->error(
                    'Missing successful prerequisite synchronization.'
                );

                $this->table(
                    ['Missing resource'],
                    array_map(
                        static fn (
                            ErpResource $resource
                        ): array => [
                            $resource->value,
                        ],
                        $missingPrerequisites
                    )
                );

                return self::FAILURE;
            }

            try {
                $run = $coordinator
                    ->synchronize(
                        resources:
                            $groups->resources(
                                $group
                            ),

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
            } catch (Throwable $exception) {
                /*
                 * Never expose tokens, authorization headers or
                 * complete ERP payloads.
                 */
                $this->components->error(
                    'ERP group synchronization could not start: '
                    .$exception->getMessage()
                );

                return self::FAILURE;
            }

            $this->displayRun(
                group: $group,
                run: $run
            );

            if (
                $run->status
                !== ErpSyncRunStatus::Completed
            ) {
                $this->components->error(
                    'Validation stopped because group ['
                    .$group->inputName()
                    .'] did not complete successfully.'
                );

                return self::FAILURE;
            }

            $this->components->success(
                'ERP group ['
                .$group->inputName()
                .'] completed successfully.'
            );
        }

        $this->newLine();

        $this->components->success(
            count($requestedGroups) === 1
                ? 'The selected ERP dependency group is valid.'
                : 'All ERP dependency groups completed successfully.'
        );

        return self::SUCCESS;
    }

    /**
     * @return list<ErpSyncGroup>|null
     */
    private function resolveGroups(
        ErpSyncGroupRegistry $registry
    ): ?array {
        $runAll =
            (bool) $this->option('all');

        $groupInput =
            $this->argument('group');

        $hasGroup =
            is_string($groupInput)
            && trim($groupInput) !== '';

        if ($runAll && $hasGroup) {
            $this->components->error(
                'Use either a group argument or --all, not both.'
            );

            return null;
        }

        if ($runAll) {
            return $registry->groups();
        }

        if (! $hasGroup) {
            $this->components->error(
                'Specify a dependency group, use --all, or use --list.'
            );

            return null;
        }

        $group = $registry->fromInput(
            (string) $groupInput
        );

        if ($group === null) {
            $this->components->error(
                'Unknown ERP synchronization group: '
                .$groupInput
            );

            return null;
        }

        return [$group];
    }

    /**
     * @return list<string>
     */
    private function preflightIssues(
        ErpSyncGroup $group,
        ErpSyncGroupRegistry $groups,
        ErpSyncTargetRegistry $targets,
        ErpConnectorInterface $connector
    ): array {
        $issues = [];

        foreach (
            $groups->resources($group)
            as $resource
        ) {
            if (
                ! $connector->supports(
                    $resource
                )
            ) {
                $issues[] =
                    'Connector does not support resource ['
                    .$resource->value
                    .'].';

                continue;
            }

            try {
                $targets->tableFor(
                    $resource
                );
            } catch (
                ErpPersistenceException
                $exception
            ) {
                $issues[] =
                    'No valid target table exists for resource ['
                    .$resource->value
                    .'].';
            }
        }

        return array_values(
            array_unique($issues)
        );
    }

    private function displayGroupCatalog(
        ErpSyncGroupRegistry $registry
    ): void {
        $rows = [];

        foreach (
            $registry->groups()
            as $group
        ) {
            $resources = array_map(
                static fn (
                    ErpResource $resource
                ): string =>
                    $resource->value,

                $registry->resources(
                    $group
                )
            );

            $prerequisites = array_map(
                static fn (
                    ErpSyncGroup $item
                ): string =>
                    $item->inputName(),

                $registry->prerequisites(
                    $group
                )
            );

            $rows[] = [
                $group->inputName(),
                $group->label(),

                implode(
                    ', ',
                    $resources
                ),

                $prerequisites === []
                    ? 'none'
                    : implode(
                        ', ',
                        $prerequisites
                    ),
            ];
        }

        $this->table(
            [
                'Group',
                'Label',
                'Resources',
                'Prerequisites',
            ],
            $rows
        );
    }

    private function displayRun(
        ErpSyncGroup $group,
        ErpSyncRun $run
    ): void {
        $run->loadMissing([
            'resources',
            'failures',
        ]);

        $this->table(
            [
                'Property',
                'Value',
            ],
            [
                [
                    'Group',
                    $group->inputName(),
                ],
                [
                    'Run UUID',
                    $run->run_uuid,
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
                    static fn (
                        $resource
                    ): array => [
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

        if ($run->failures->isEmpty()) {
            return;
        }

        /*
         * Stored failure messages and contexts were already
         * sanitized by ErpSyncRunTracker.
         */
        $this->components->warn(
            'Safe ERP failure details'
        );

        $this->table(
            [
                'Resource',
                'Stage',
                'External ID',
                'Page',
                'Error code',
                'Message',
            ],
            $run->failures
                ->map(
                    static fn (
                        $failure
                    ): array => [
                        $failure
                            ->resource
                            ->value,

                        $failure
                            ->stage
                            ->value,

                        $failure->external_id
                            ?? '',

                        $failure->page
                            ?? '',

                        $failure->error_code,

                        $failure->error_message,
                    ]
                )
                ->all()
        );
    }

    private function positiveIntegerOption(
        string $name,
        int $minimum,
        int $maximum
    ): int|false|null {
        $value = $this->option(
            $name
        );

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
                    'min_range' =>
                        $minimum,

                    'max_range' =>
                        $maximum,
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