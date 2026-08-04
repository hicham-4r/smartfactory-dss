<?php

namespace App\Services\ERP\Sync;

use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncFailureStage;
use App\Enums\ERP\ErpSyncResourceStatus;
use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Models\ErpSyncFailure;
use App\Models\ErpSyncRun;
use App\Models\ErpSyncRunResource;
use App\Models\ErpSyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class ErpSyncRunTracker
{
    /**
     * @param list<ErpResource> $resources
     */
    public function startRun(
        string $sourceSystem,
        array $resources,
        ErpSyncTrigger $trigger =
            ErpSyncTrigger::Manual,
        ?int $initiatedByUserId = null,
        ?string $requestId = null
    ): ErpSyncRun {
        $sourceSystem =
            $this->normalizeSourceSystem(
                $sourceSystem
            );

        $resources =
            $this->normalizeResources(
                $resources
            );

        $requestId =
            $this->normalizeOptionalIdentifier(
                $requestId,
                100
            );

        return DB::transaction(
            function () use (
                $sourceSystem,
                $resources,
                $trigger,
                $initiatedByUserId,
                $requestId
            ): ErpSyncRun {
                $now = CarbonImmutable::now();

                $run = ErpSyncRun::query()
                    ->create([
                        'run_uuid' =>
                            (string) Str::uuid(),

                        'source_system' =>
                            $sourceSystem,

                        'trigger' =>
                            $trigger,

                        'status' =>
                            ErpSyncRunStatus::Running,

                        'initiated_by_user_id' =>
                            $initiatedByUserId,

                        'request_id' =>
                            $requestId,

                        'requested_resources' =>
                            array_map(
                                static fn (
                                    ErpResource $resource
                                ): string =>
                                    $resource->value,
                                $resources
                            ),

                        'started_at' => $now,
                    ]);

                foreach ($resources as $resource) {
                    $run->resources()->create([
                        'resource' =>
                            $resource,

                        'status' =>
                            ErpSyncResourceStatus
                                ::Pending,
                    ]);

                    ErpSyncState::query()
                        ->firstOrCreate(
                            [
                                'source_system' =>
                                    $sourceSystem,

                                'resource' =>
                                    $resource->value,
                            ],
                            [
                                'resume_page' => 1,
                            ]
                        );
                }

                return $run->load(
                    'resources'
                );
            },
            attempts: 3
        );
    }

    public function startResource(
        ErpSyncRun $run,
        ErpResource $resource
    ): ErpSyncRunResource {
        return DB::transaction(
            function () use (
                $run,
                $resource
            ): ErpSyncRunResource {
                $lockedRun = ErpSyncRun::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $run->getKey()
                    );

                $this->assertRunRunning(
                    $lockedRun
                );

                $runResource =
                    ErpSyncRunResource::query()
                        ->where(
                            'erp_sync_run_id',
                            $lockedRun->getKey()
                        )
                        ->where(
                            'resource',
                            $resource->value
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $runResource->status
                    === ErpSyncResourceStatus::Running
                ) {
                    return $runResource;
                }

                if (
                    $runResource->status
                    !== ErpSyncResourceStatus::Pending
                ) {
                    throw new LogicException(
                        'Only a pending ERP resource may be started.'
                    );
                }

                $runResource->forceFill([
                    'status' =>
                        ErpSyncResourceStatus::Running,

                    'started_at' =>
                        CarbonImmutable::now(),

                    'finished_at' => null,
                    'error_code' => null,
                    'error_message' => null,
                ])->save();

                ErpSyncState::query()
                    ->where(
                        'source_system',
                        $lockedRun->source_system
                    )
                    ->where(
                        'resource',
                        $resource->value
                    )
                    ->update([
                        'last_run_id' =>
                            $lockedRun->getKey(),

                        'updated_at' =>
                            CarbonImmutable::now(),
                    ]);

                return $runResource->fresh();
            },
            attempts: 3
        );
    }

    public function recordPage(
        ErpSyncRunResource $runResource,
        int $fetched,
        int $mapped,
        int $created,
        int $updated,
        int $skipped,
        int $failed
    ): ErpSyncRunResource {
        $this->assertNonNegativeCounts([
            'fetched' => $fetched,
            'mapped' => $mapped,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return DB::transaction(
            function () use (
                $runResource,
                $fetched,
                $mapped,
                $created,
                $updated,
                $skipped,
                $failed
            ): ErpSyncRunResource {
                $locked =
                    ErpSyncRunResource::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $runResource->getKey()
                        );

                $this->assertResourceRunning(
                    $locked
                );

                $locked->forceFill([
                    'pages_processed' =>
                        $locked->pages_processed + 1,

                    'records_fetched' =>
                        $locked->records_fetched
                        + $fetched,

                    'records_mapped' =>
                        $locked->records_mapped
                        + $mapped,

                    'records_created' =>
                        $locked->records_created
                        + $created,

                    'records_updated' =>
                        $locked->records_updated
                        + $updated,

                    'records_skipped' =>
                        $locked->records_skipped
                        + $skipped,

                    'records_failed' =>
                        $locked->records_failed
                        + $failed,
                ])->save();

                return $locked->fresh();
            },
            attempts: 3
        );
    }

    public function saveCheckpoint(
        ErpSyncRunResource $runResource,
        int $nextPage,
        ?string $resumeCursor,
        ?CarbonImmutable $lastSourceUpdatedAt,
        ?int $lastSourceVersion
    ): ErpSyncState {
        if ($nextPage < 1) {
            throw new InvalidArgumentException(
                'The next ERP page must be at least one.'
            );
        }

        $resumeCursor =
            $this->normalizeCursor(
                $resumeCursor
            );

        if (
            $lastSourceVersion !== null
            && $lastSourceVersion < 0
        ) {
            throw new InvalidArgumentException(
                'ERP source version cannot be negative.'
            );
        }

        return DB::transaction(
            function () use (
                $runResource,
                $nextPage,
                $resumeCursor,
                $lastSourceUpdatedAt,
                $lastSourceVersion
            ): ErpSyncState {
                $lockedResource =
                    ErpSyncRunResource::query()
                        ->with('run')
                        ->lockForUpdate()
                        ->findOrFail(
                            $runResource->getKey()
                        );

                $this->assertResourceRunning(
                    $lockedResource
                );

                $run = $lockedResource->run;

                $state = ErpSyncState::query()
                    ->where(
                        'source_system',
                        $run->source_system
                    )
                    ->where(
                        'resource',
                        $lockedResource
                            ->resource
                            ->value
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $effectiveTimestamp =
                    $state->last_source_updated_at;

                if (
                    $lastSourceUpdatedAt !== null
                    && (
                        $effectiveTimestamp === null
                        || $lastSourceUpdatedAt
                            ->greaterThan(
                                $effectiveTimestamp
                            )
                    )
                ) {
                    $effectiveTimestamp =
                        $lastSourceUpdatedAt;
                }

                $effectiveVersion =
                    $state->last_source_version;

                if (
                    $lastSourceVersion !== null
                    && (
                        $effectiveVersion === null
                        || $lastSourceVersion
                            > $effectiveVersion
                    )
                ) {
                    $effectiveVersion =
                        $lastSourceVersion;
                }

                $cursorFingerprint =
                    $this->cursorFingerprint(
                        $resumeCursor
                    );

                $state->forceFill([
                    'resume_page' =>
                        $nextPage,

                    'resume_cursor' =>
                        $resumeCursor,

                    'resume_cursor_fingerprint' =>
                        $cursorFingerprint,

                    'last_source_updated_at' =>
                        $effectiveTimestamp,

                    'last_source_version' =>
                        $effectiveVersion,

                    'last_run_id' =>
                        $run->getKey(),
                ])->save();

                $lockedResource->forceFill([
                    'last_source_updated_at' =>
                        $effectiveTimestamp,

                    'last_source_version' =>
                        $effectiveVersion,

                    'last_cursor_fingerprint' =>
                        $cursorFingerprint,
                ])->save();

                return $state->fresh();
            },
            attempts: 3
        );
    }

    public function completeResource(
        ErpSyncRunResource $runResource
    ): ErpSyncRunResource {
        return DB::transaction(
            function () use (
                $runResource
            ): ErpSyncRunResource {
                $locked =
                    ErpSyncRunResource::query()
                        ->with('run')
                        ->lockForUpdate()
                        ->findOrFail(
                            $runResource->getKey()
                        );

                if (
                    $locked->status
                    === ErpSyncResourceStatus::Completed
                ) {
                    return $locked;
                }

                $this->assertResourceRunning(
                    $locked
                );

                $now = CarbonImmutable::now();

                $locked->forceFill([
                    'status' =>
                        ErpSyncResourceStatus
                            ::Completed,

                    'finished_at' => $now,
                    'error_code' => null,
                    'error_message' => null,
                ])->save();

                ErpSyncState::query()
                    ->where(
                        'source_system',
                        $locked->run
                            ->source_system
                    )
                    ->where(
                        'resource',
                        $locked->resource->value
                    )
                    ->update([
                        'last_successful_sync_at' =>
                            $now,

                        'resume_page' => 1,
                        'resume_cursor' => null,

                        'resume_cursor_fingerprint' =>
                            null,

                        'last_run_id' =>
                            $locked->run->getKey(),

                        'consecutive_failures' => 0,
                        'last_error_code' => null,
                        'last_error_message' => null,
                        'updated_at' => $now,
                    ]);

                return $locked->fresh();
            },
            attempts: 3
        );
    }

    public function skipResource(
        ErpSyncRunResource $runResource
    ): ErpSyncRunResource {
        return DB::transaction(
            function () use (
                $runResource
            ): ErpSyncRunResource {
                $locked =
                    ErpSyncRunResource::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $runResource->getKey()
                        );

                if (
                    ! in_array(
                        $locked->status,
                        [
                            ErpSyncResourceStatus
                                ::Pending,

                            ErpSyncResourceStatus
                                ::Running,
                        ],
                        true
                    )
                ) {
                    throw new LogicException(
                        'Only a pending or running ERP resource may be skipped.'
                    );
                }

                $locked->forceFill([
                    'status' =>
                        ErpSyncResourceStatus::Skipped,

                    'finished_at' =>
                        CarbonImmutable::now(),
                ])->save();

                return $locked->fresh();
            },
            attempts: 3
        );
    }

    /**
     * @param array<string, mixed> $safeContext
     */
    public function failResource(
        ErpSyncRunResource $runResource,
        ErpSyncFailureStage $stage,
        string $errorCode,
        string $errorMessage,
        bool $retryable,
        ?string $externalId = null,
        ?int $page = null,
        ?string $cursor = null,
        array $safeContext = []
    ): ErpSyncFailure {
        if ($page !== null && $page < 1) {
            throw new InvalidArgumentException(
                'The failed ERP page must be at least one.'
            );
        }

        $errorCode =
            $this->normalizeErrorCode(
                $errorCode
            );

        $errorMessage =
            $this->sanitizeMessage(
                $errorMessage
            );

        $externalId =
            $this->normalizeOptionalIdentifier(
                $externalId,
                120
            );

        $cursor =
            $this->normalizeCursor(
                $cursor
            );

        $safeContext =
            $this->redactContext(
                $safeContext
            );

        return DB::transaction(
            function () use (
                $runResource,
                $stage,
                $errorCode,
                $errorMessage,
                $retryable,
                $externalId,
                $page,
                $cursor,
                $safeContext
            ): ErpSyncFailure {
                $locked =
                    ErpSyncRunResource::query()
                        ->with('run')
                        ->lockForUpdate()
                        ->findOrFail(
                            $runResource->getKey()
                        );

                if (
                    $locked->status->isTerminal()
                    && $locked->status
                        !== ErpSyncResourceStatus
                            ::Failed
                ) {
                    throw new LogicException(
                        'A completed or skipped ERP resource cannot be failed.'
                    );
                }

                $now = CarbonImmutable::now();

                $locked->forceFill([
                    'status' =>
                        ErpSyncResourceStatus::Failed,

                    'error_code' =>
                        $errorCode,

                    'error_message' =>
                        $errorMessage,

                    'finished_at' =>
                        $now,
                ])->save();

                $failure = ErpSyncFailure::query()
                    ->create([
                        'erp_sync_run_id' =>
                            $locked->erp_sync_run_id,

                        'erp_sync_run_resource_id' =>
                            $locked->getKey(),

                        'resource' =>
                            $locked->resource,

                        'stage' =>
                            $stage,

                        'external_id' =>
                            $externalId,

                        'page' =>
                            $page,

                        'cursor_fingerprint' =>
                            $this->cursorFingerprint(
                                $cursor
                            ),

                        'error_code' =>
                            $errorCode,

                        'error_message' =>
                            $errorMessage,

                        'retryable' =>
                            $retryable,

                        'safe_context' =>
                            $safeContext === []
                                ? null
                                : $safeContext,

                        'occurred_at' =>
                            $now,
                    ]);

                $state = ErpSyncState::query()
                    ->where(
                        'source_system',
                        $locked->run
                            ->source_system
                    )
                    ->where(
                        'resource',
                        $locked->resource->value
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $state->forceFill([
                    'last_run_id' =>
                        $locked->run->getKey(),

                    'consecutive_failures' =>
                        $state
                            ->consecutive_failures
                        + 1,

                    'last_error_code' =>
                        $errorCode,

                    'last_error_message' =>
                        $errorMessage,
                ])->save();

                return $failure->fresh();
            },
            attempts: 3
        );
    }

    public function finalizeRun(
        ErpSyncRun $run
    ): ErpSyncRun {
        return DB::transaction(
            function () use (
                $run
            ): ErpSyncRun {
                $lockedRun = ErpSyncRun::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $run->getKey()
                    );

                if ($lockedRun->status->isTerminal()) {
                    return $lockedRun->fresh(
                        'resources'
                    );
                }

                $resources =
                    ErpSyncRunResource::query()
                        ->where(
                            'erp_sync_run_id',
                            $lockedRun->getKey()
                        )
                        ->lockForUpdate()
                        ->get();

                if ($resources->isEmpty()) {
                    throw new LogicException(
                        'An ERP synchronization run must contain at least one resource.'
                    );
                }

                $unfinished =
                    $resources->contains(
                        static fn (
                            ErpSyncRunResource $resource
                        ): bool =>
                            ! $resource
                                ->status
                                ->isTerminal()
                    );

                if ($unfinished) {
                    throw new LogicException(
                        'The ERP synchronization run cannot be finalized while resources are pending or running.'
                    );
                }

                $failedCount =
                    $resources->where(
                        'status',
                        ErpSyncResourceStatus::Failed
                    )->count();

                $completedCount =
                    $resources->where(
                        'status',
                        ErpSyncResourceStatus
                            ::Completed
                    )->count();

                $status = match (true) {
                    $failedCount ===
                        $resources->count() =>
                            ErpSyncRunStatus::Failed,

                    $failedCount > 0 =>
                        ErpSyncRunStatus
                            ::CompletedWithErrors,

                    default =>
                        ErpSyncRunStatus::Completed,
                };

                $lockedRun->forceFill([
                    'status' => $status,

                    'pages_processed' =>
                        $this->sum(
                            $resources,
                            'pages_processed'
                        ),

                    'records_fetched' =>
                        $this->sum(
                            $resources,
                            'records_fetched'
                        ),

                    'records_mapped' =>
                        $this->sum(
                            $resources,
                            'records_mapped'
                        ),

                    'records_created' =>
                        $this->sum(
                            $resources,
                            'records_created'
                        ),

                    'records_updated' =>
                        $this->sum(
                            $resources,
                            'records_updated'
                        ),

                    'records_skipped' =>
                        $this->sum(
                            $resources,
                            'records_skipped'
                        ),

                    'records_failed' =>
                        $this->sum(
                            $resources,
                            'records_failed'
                        ),

                    'error_code' =>
                        $failedCount > 0
                            ? 'RESOURCE_FAILURE'
                            : null,

                    'error_message' =>
                        $failedCount > 0
                            ? (
                                $failedCount
                                .' ERP resource(s) failed; '
                                .$completedCount
                                .' completed.'
                            )
                            : null,

                    'finished_at' =>
                        CarbonImmutable::now(),
                ])->save();

                return $lockedRun->fresh(
                    'resources'
                );
            },
            attempts: 3
        );
    }

    public function acquireResourceLease(
        string $sourceSystem,
        ErpResource $resource,
        string $owner,
        int $timeToLiveSeconds = 300
    ): bool {
        $sourceSystem =
            $this->normalizeSourceSystem(
                $sourceSystem
            );

        $owner =
            $this->normalizeRequiredIdentifier(
                $owner,
                100
            );

        if (
            $timeToLiveSeconds < 30
            || $timeToLiveSeconds > 3600
        ) {
            throw new InvalidArgumentException(
                'ERP synchronization lease duration must be between 30 and 3600 seconds.'
            );
        }

        return DB::transaction(
            function () use (
                $sourceSystem,
                $resource,
                $owner,
                $timeToLiveSeconds
            ): bool {
                ErpSyncState::query()
                    ->firstOrCreate(
                        [
                            'source_system' =>
                                $sourceSystem,

                            'resource' =>
                                $resource->value,
                        ],
                        [
                            'resume_page' => 1,
                        ]
                    );

                $state = ErpSyncState::query()
                    ->where(
                        'source_system',
                        $sourceSystem
                    )
                    ->where(
                        'resource',
                        $resource->value
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $now = CarbonImmutable::now();

                $expiredBefore =
                    $now->subSeconds(
                        $timeToLiveSeconds
                    );

                $leaseIsActive =
                    $state->lock_owner !== null
                    && $state->lock_acquired_at
                        !== null
                    && $state->lock_acquired_at
                        ->greaterThan(
                            $expiredBefore
                        );

                if (
                    $leaseIsActive
                    && $state->lock_owner
                        !== $owner
                ) {
                    return false;
                }

                $state->forceFill([
                    'lock_owner' => $owner,

                    'lock_acquired_at' =>
                        $now,
                ])->save();

                return true;
            },
            attempts: 3
        );
    }

    public function releaseResourceLease(
        string $sourceSystem,
        ErpResource $resource,
        string $owner
    ): bool {
        $sourceSystem =
            $this->normalizeSourceSystem(
                $sourceSystem
            );

        $owner =
            $this->normalizeRequiredIdentifier(
                $owner,
                100
            );

        return DB::transaction(
            function () use (
                $sourceSystem,
                $resource,
                $owner
            ): bool {
                $state = ErpSyncState::query()
                    ->where(
                        'source_system',
                        $sourceSystem
                    )
                    ->where(
                        'resource',
                        $resource->value
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    $state === null
                    || $state->lock_owner
                        !== $owner
                ) {
                    return false;
                }

                $state->forceFill([
                    'lock_owner' => null,
                    'lock_acquired_at' => null,
                ])->save();

                return true;
            },
            attempts: 3
        );
    }

    private function assertRunRunning(
        ErpSyncRun $run
    ): void {
        if (
            $run->status
            !== ErpSyncRunStatus::Running
        ) {
            throw new LogicException(
                'The ERP synchronization run is not running.'
            );
        }
    }

    private function assertResourceRunning(
        ErpSyncRunResource $resource
    ): void {
        if (
            $resource->status
            !== ErpSyncResourceStatus::Running
        ) {
            throw new LogicException(
                'The ERP synchronization resource is not running.'
            );
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private function assertNonNegativeCounts(
        array $counts
    ): void {
        foreach ($counts as $name => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException(
                    "ERP synchronization count [{$name}] cannot be negative."
                );
            }
        }
    }

    /**
     * @param list<ErpResource> $resources
     *
     * @return list<ErpResource>
     */
    private function normalizeResources(
        array $resources
    ): array {
        if ($resources === []) {
            throw new InvalidArgumentException(
                'At least one ERP resource is required.'
            );
        }

        $normalized = [];

        foreach ($resources as $resource) {
            if (! $resource instanceof ErpResource) {
                throw new InvalidArgumentException(
                    'ERP synchronization resources must be ErpResource enum values.'
                );
            }

            $normalized[
                $resource->value
            ] = $resource;
        }

        return array_values(
            $normalized
        );
    }

    private function normalizeSourceSystem(
        string $sourceSystem
    ): string {
        $sourceSystem = strtolower(
            trim($sourceSystem)
        );

        if (
            $sourceSystem === ''
            || mb_strlen($sourceSystem) > 80
            || ! preg_match(
                '/^[a-z0-9][a-z0-9._-]*$/',
                $sourceSystem
            )
        ) {
            throw new InvalidArgumentException(
                'The ERP source-system identifier is invalid.'
            );
        }

        return $sourceSystem;
    }

    private function normalizeCursor(
        ?string $cursor
    ): ?string {
        if ($cursor === null) {
            return null;
        }

        $cursor = trim($cursor);

        if ($cursor === '') {
            return null;
        }

        if (
            strlen($cursor) > 4000
            || preg_match(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                $cursor
            )
        ) {
            throw new InvalidArgumentException(
                'The ERP resume cursor is invalid.'
            );
        }

        return $cursor;
    }

    private function cursorFingerprint(
        ?string $cursor
    ): ?string {
        return $cursor === null
            ? null
            : hash('sha256', $cursor);
    }

    private function normalizeErrorCode(
        string $errorCode
    ): string {
        $errorCode = strtoupper(
            trim($errorCode)
        );

        $errorCode = preg_replace(
            '/[^A-Z0-9._:-]+/',
            '_',
            $errorCode
        ) ?? '';

        $errorCode = trim(
            $errorCode,
            '_'
        );

        if ($errorCode === '') {
            return 'ERP_SYNC_ERROR';
        }

        return Str::limit(
            $errorCode,
            100,
            ''
        );
    }

    private function sanitizeMessage(
        string $message
    ): string {
        $message = preg_replace(
            '/Bearer\s+\S+/i',
            'Bearer [REDACTED]',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/\b(token|secret|password|authorization|api[_-]?key)\b\s*[:=]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
            ' ',
            $message
        ) ?? $message;

        $message = preg_replace(
            '/\s+/',
            ' ',
            trim($message)
        ) ?? trim($message);

        if ($message === '') {
            $message =
                'ERP synchronization failure.';
        }

        return Str::limit(
            $message,
            1000,
            ''
        );
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function redactContext(
        array $context,
        int $depth = 0
    ): array {
        if ($depth >= 5) {
            return [
                'truncated' => true,
            ];
        }

        $safe = [];
        $processed = 0;

        foreach ($context as $key => $value) {
            if ($processed >= 50) {
                $safe['context_truncated'] = true;

                break;
            }

            $processed++;

            $key = (string) $key;

            if (
                preg_match(
                    '/password|token|secret|authorization|two_factor|recovery|api[_-]?key/i',
                    $key
                )
            ) {
                $safe[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $safe[$key] =
                    $this->redactContext(
                        $value,
                        $depth + 1
                    );

                continue;
            }

            if (
                is_string($value)
                || is_int($value)
                || is_float($value)
                || is_bool($value)
                || $value === null
            ) {
                $safe[$key] = is_string($value)
                    ? $this->sanitizeMessage(
                        $value
                    )
                    : $value;

                continue;
            }

            $safe[$key] = [
                'type' =>
                    get_debug_type($value),
            ];
        }

        return $safe;
    }

    private function normalizeOptionalIdentifier(
        ?string $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $this->normalizeRequiredIdentifier(
            $value,
            $maximumLength
        );
    }

    private function normalizeRequiredIdentifier(
        string $value,
        int $maximumLength
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || mb_strlen($value)
                > $maximumLength
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $value
            )
        ) {
            throw new InvalidArgumentException(
                'The ERP synchronization identifier is invalid.'
            );
        }

        return $value;
    }

    private function sum(
        Collection $resources,
        string $column
    ): int {
        return (int) $resources->sum(
            $column
        );
    }
}