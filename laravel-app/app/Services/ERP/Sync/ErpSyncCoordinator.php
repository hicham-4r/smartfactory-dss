<?php

namespace App\Services\ERP\Sync;

use App\Contracts\ERP\ErpConnectorInterface;
use App\Contracts\ERP\ErpRecordMapperInterface;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\ErpSyncCursor;
use App\Enums\ERP\ErpPersistenceAction;
use App\Enums\ERP\ErpResource;
use App\Enums\ERP\ErpSyncFailureStage;
use App\Enums\ERP\ErpSyncResourceStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Exceptions\ERP\ErpConnectorException;
use App\Exceptions\ERP\ErpInvalidResponseException;
use App\Exceptions\ERP\ErpMappingException;
use App\Exceptions\ERP\ErpPersistenceException;
use App\Models\ErpSyncRun;
use App\Models\ErpSyncRunResource;
use App\Models\ErpSyncState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

final class ErpSyncCoordinator
{
    public function __construct(
        private readonly ErpConnectorInterface $connector,
        private readonly ErpRecordMapperInterface $mapper,
        private readonly ErpMappedEntityPersister $persister,
        private readonly ErpSyncRunTracker $tracker
    ) {
    }

    /**
     * @param list<ErpResource> $resources
     */
    public function synchronize(
        array $resources,
        ErpSyncTrigger $trigger =
            ErpSyncTrigger::Manual,
        ?int $initiatedByUserId = null,
        ?string $requestId = null,
        ?int $perPage = null,
        ?int $maximumPagesPerResource = null,
        bool $fromStart = false
    ): ErpSyncRun {
        $resources =
            $this->normalizeResources(
                $resources
            );

        $perPage ??= (int) config(
            'erp-sync.page_size',
            100
        );

        $maximumPagesPerResource ??=
            (int) config(
                'erp-sync.maximum_pages_per_resource',
                10000
            );

        $this->validateLimits(
            perPage: $perPage,

            maximumPagesPerResource:
                $maximumPagesPerResource
        );

        $requestId ??=
            (string) Str::uuid();

        $run = $this->tracker->startRun(
            sourceSystem:
                $this->connector
                    ->sourceSystem(),

            resources: $resources,
            trigger: $trigger,

            initiatedByUserId:
                $initiatedByUserId,

            requestId:
                $requestId
        );

        foreach ($resources as $resource) {
            $this->synchronizeResource(
                run: $run,
                resource: $resource,
                perPage: $perPage,

                maximumPages:
                    $maximumPagesPerResource,

                fromStart: $fromStart
            );
        }

        return $this->tracker
            ->finalizeRun($run);
    }

    private function synchronizeResource(
        ErpSyncRun $run,
        ErpResource $resource,
        int $perPage,
        int $maximumPages,
        bool $fromStart
    ): void {
        $runResource = $run->resources
            ->first(
                static fn (
                    ErpSyncRunResource $item
                ): bool =>
                    $item->resource === $resource
            );

        if (! $runResource) {
            throw new LogicException(
                'The ERP synchronization resource row is missing.'
            );
        }

        $leaseOwner =
            $run->run_uuid
            .':'
            .$resource->value;

        $leaseAcquired =
            $this->tracker
                ->acquireResourceLease(
                    sourceSystem:
                        $run->source_system,

                    resource:
                        $resource,

                    owner:
                        $leaseOwner,

                    timeToLiveSeconds:
                        (int) config(
                            'erp-sync.lease_ttl_seconds',
                            300
                        )
                );

        if (! $leaseAcquired) {
            $this->tracker->skipResource(
                $runResource
            );

            return;
        }

        $currentRequest = null;
        $currentExternalId = null;

        try {
            if (
                ! $this->connector
                    ->supports($resource)
            ) {
                throw new InvalidArgumentException(
                    'The configured ERP connector does not support resource ['
                    .$resource->value
                    .'].'
                );
            }

            $runResource =
                $this->tracker
                    ->startResource(
                        $run,
                        $resource
                    );

            $state = ErpSyncState::query()
                ->where(
                    'source_system',
                    $run->source_system
                )
                ->where(
                    'resource',
                    $resource->value
                )
                ->firstOrFail();

            $currentRequest =
                $this->initialRequest(
                    state: $state,
                    perPage: $perPage,
                    fromStart: $fromStart
                );

            $pagesProcessed = 0;

            while (true) {
                if (
                    $pagesProcessed
                    >= $maximumPages
                ) {
                    throw new LogicException(
                        'The ERP synchronization exceeded the configured page limit.'
                    );
                }

                $page = $this->connector
                    ->fetchPage(
                        $resource,
                        $currentRequest
                    );

                $fetched = count(
                    $page->records
                );

                $mapped = 0;
                $created = 0;
                $updated = 0;
                $skipped = 0;
                $failed = 0;

                $lastSourceUpdatedAt = null;
                $lastSourceVersion = null;

                try {
                    foreach (
                        $page->records
                        as $sourceRecord
                    ) {
                        $currentExternalId =
                            $sourceRecord
                                ->identity
                                ->externalId;

                        if (
                            $sourceRecord
                                ->sourceUpdatedAt
                            !== null
                            && (
                                $lastSourceUpdatedAt
                                === null
                                || $sourceRecord
                                    ->sourceUpdatedAt
                                    ->greaterThan(
                                        $lastSourceUpdatedAt
                                    )
                            )
                        ) {
                            $lastSourceUpdatedAt =
                                $sourceRecord
                                    ->sourceUpdatedAt;
                        }

                        if (
                            $sourceRecord
                                ->sourceVersion
                            !== null
                            && (
                                $lastSourceVersion
                                === null
                                || $sourceRecord
                                    ->sourceVersion
                                    > $lastSourceVersion
                            )
                        ) {
                            $lastSourceVersion =
                                $sourceRecord
                                    ->sourceVersion;
                        }

                        $mappedEntity =
                            $this->mapper->map(
                                $sourceRecord
                            );

                        $mapped++;

                        $result =
                            $this->persister
                                ->persist(
                                    $mappedEntity
                                );

                        match ($result->action) {
                            ErpPersistenceAction::Created =>
                                $created++,

                            ErpPersistenceAction::Updated =>
                                $updated++,

                            ErpPersistenceAction::Skipped =>
                                $skipped++,
                        };
                    }
                } catch (Throwable $exception) {
                    $failed++;

                    /*
                     * Records already written before the failure remain safe.
                     * The page checkpoint is not advanced, so a retry processes
                     * the page again and the idempotent persister skips them.
                     */
                    $runResource =
                        $this->tracker->recordPage(
                            runResource:
                                $runResource,

                            fetched: $fetched,
                            mapped: $mapped,
                            created: $created,
                            updated: $updated,
                            skipped: $skipped,
                            failed: $failed
                        );

                    throw $exception;
                }

                $runResource =
                    $this->tracker->recordPage(
                        runResource:
                            $runResource,

                        fetched: $fetched,
                        mapped: $mapped,
                        created: $created,
                        updated: $updated,
                        skipped: $skipped,
                        failed: $failed
                    );

                $nextRequest =
                    $page->nextRequest(
                        $currentRequest
                    );

                $nextPage =
                    $nextRequest?->page
                    ?? (
                        $currentRequest->page
                        + 1
                    );

                $nextCursor =
                    $nextRequest
                        ?->cursor
                        ?->opaqueToken;

                $this->tracker
                    ->saveCheckpoint(
                        runResource:
                            $runResource,

                        nextPage:
                            $nextPage,

                        resumeCursor:
                            $nextCursor,

                        lastSourceUpdatedAt:
                            $lastSourceUpdatedAt,

                        lastSourceVersion:
                            $lastSourceVersion
                    );

                $pagesProcessed++;

                if ($nextRequest === null) {
                    $this->tracker
                        ->completeResource(
                            $runResource
                        );

                    break;
                }

                $currentRequest =
                    $nextRequest;

                $currentExternalId = null;
            }
        } catch (Throwable $exception) {
            $freshResource =
                $runResource->fresh();

            if (
                $freshResource !== null
                && ! $freshResource
                    ->status
                    ->isTerminal()
            ) {
                $this->tracker
                    ->failResource(
                        runResource:
                            $freshResource,

                        stage:
                            $this->failureStage(
                                $exception
                            ),

                        errorCode:
                            $this->errorCode(
                                $exception
                            ),

                        errorMessage:
                            $exception
                                ->getMessage(),

                        retryable:
                            $this->isRetryable(
                                $exception
                            ),

                        externalId:
                            $currentExternalId,

                        page:
                            $currentRequest?->page,

                        cursor:
                            $currentRequest
                                ?->cursor
                                ?->opaqueToken,

                        safeContext:
                            $this->safeContext(
                                $exception
                            )
                    );
            }
        } finally {
            $this->tracker
                ->releaseResourceLease(
                    sourceSystem:
                        $run->source_system,

                    resource:
                        $resource,

                    owner:
                        $leaseOwner
                );
        }
    }

    private function initialRequest(
        ErpSyncState $state,
        int $perPage,
        bool $fromStart
    ): ErpPageRequest {
        if ($fromStart) {
            return new ErpPageRequest(
                page: 1,
                perPage: $perPage
            );
        }

        $updatedSince =
            $state->last_source_updated_at;

        if ($updatedSince !== null) {
            $updatedSince =
                $updatedSince->subSeconds(
                    max(
                        0,
                        (int) config(
                            'erp-sync.overlap_seconds',
                            300
                        )
                    )
                );
        }

        $resumeCursor =
            $state->resume_cursor;

        $sourceVersion =
            $state->last_source_version;

        $cursor = (
            $updatedSince !== null
            || $resumeCursor !== null
            || $sourceVersion !== null
        )
            ? new ErpSyncCursor(
                updatedSince:
                    $updatedSince,

                opaqueToken:
                    $resumeCursor,

                sourceVersion:
                    $sourceVersion
            )
            : null;

        return new ErpPageRequest(
            page: max(
                1,
                $state->resume_page
            ),

            perPage: $perPage,
            cursor: $cursor
        );
    }

    private function failureStage(
        Throwable $exception
    ): ErpSyncFailureStage {
        return match (true) {
            $exception
                instanceof ErpInvalidResponseException =>
                    ErpSyncFailureStage::Response,

            $exception
                instanceof ErpMappingException =>
                    ErpSyncFailureStage::Mapping,

            $exception
                instanceof ErpPersistenceException =>
                    ErpSyncFailureStage::Persistence,

            $exception
                instanceof ErpConnectorException =>
                    ErpSyncFailureStage::Connector,

            default =>
                ErpSyncFailureStage::Finalization,
        };
    }

    private function errorCode(
        Throwable $exception
    ): string {
        return Str::upper(
            Str::snake(
                class_basename(
                    $exception
                )
            )
        );
    }

    private function isRetryable(
        Throwable $exception
    ): bool {
        if (
            method_exists(
                $exception,
                'isRetryable'
            )
        ) {
            return (bool) $exception
                ->isRetryable();
        }

        return $exception
            instanceof ErpPersistenceException;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeContext(
        Throwable $exception
    ): array {
        if (
            method_exists(
                $exception,
                'context'
            )
        ) {
            $context =
                $exception->context();

            return is_array($context)
                ? $context
                : [];
        }

        return [
            'exception_type' =>
                class_basename(
                    $exception
                ),
        ];
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
                'At least one ERP resource must be synchronized.'
            );
        }

        $normalized = [];

        foreach ($resources as $resource) {
            if (
                ! $resource
                instanceof ErpResource
            ) {
                throw new InvalidArgumentException(
                    'Synchronization resources must use ErpResource enum values.'
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

    private function validateLimits(
        int $perPage,
        int $maximumPagesPerResource
    ): void {
        if (
            $perPage < 1
            || $perPage > 200
        ) {
            throw new InvalidArgumentException(
                'ERP synchronization page size must be between 1 and 200.'
            );
        }

        if (
            $maximumPagesPerResource < 1
            || $maximumPagesPerResource > 100000
        ) {
            throw new InvalidArgumentException(
                'ERP synchronization page limit must be between 1 and 100000.'
            );
        }
    }
}