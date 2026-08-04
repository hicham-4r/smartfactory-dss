<?php

namespace App\Services\ERP\Sync;

use App\Contracts\ERP\ErpMappedEntityInterface;
use App\DTOs\ERP\ErpPersistenceResult;
use App\Enums\ERP\ErpPersistenceAction;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpPersistenceException;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Stringable;
use Throwable;

final class ErpMappedEntityPersister
{
    public function __construct(
        private readonly ErpSyncTargetRegistry $registry
    ) {
    }

    public function persist(
        ErpMappedEntityInterface $entity
    ): ErpPersistenceResult {
        $resource = $entity->resource();
        $source = $entity->source();

        $sourceSystem =
            $source->identity->sourceSystem;

        $externalId =
            $source->identity->externalId;

        $table =
            $this->registry->tableFor(
                $resource
            );

        $columns =
            $this->registry->columnsFor(
                $table
            );

        $rawData =
            $this->mappedData(
                $entity,
                $externalId
            );

        $rawData =
            $this->resolveRelationships(
                resource: $resource,
                sourceSystem: $sourceSystem,
                externalId: $externalId,
                data: $rawData,
                targetColumns: $columns
            );

        $payload = $this->buildPayload(
            resource: $resource,
            data: $rawData,
            columns: $columns,
            sourceSystem: $sourceSystem,
            externalId: $externalId,

            sourceVersion:
                $source->sourceVersion,

            sourceChecksum:
                $source->checksum,

            sourceUpdatedAt:
                $source->sourceUpdatedAt
        );

        $identity = $this->identity(
            resource: $resource,
            table: $table,
            columns: $columns,
            payload: $payload,
            sourceSystem: $sourceSystem,
            externalId: $externalId
        );

        try {
            return DB::transaction(
                function () use (
                    $resource,
                    $table,
                    $columns,
                    $payload,
                    $identity,
                    $externalId
                ): ErpPersistenceResult {
                    $existing = DB::table($table)
                        ->where($identity)
                        ->lockForUpdate()
                        ->first();

                    if ($existing === null) {
                        $recordId = DB::table($table)
                            ->insertGetId($payload);

                        return new ErpPersistenceResult(
                            resource: $resource,

                            action:
                                ErpPersistenceAction::Created,

                            table: $table,
                            externalId: $externalId,
                            recordId: $recordId
                        );
                    }

                    if (
                        $this->shouldSkip(
                            existing: $existing,
                            payload: $payload,
                            columns: $columns
                        )
                    ) {
                        $this->touchSkippedRecord(
                            table: $table,
                            identity: $identity,
                            columns: $columns
                        );

                        return new ErpPersistenceResult(
                            resource: $resource,

                            action:
                                ErpPersistenceAction::Skipped,

                            table: $table,
                            externalId: $externalId,

                            recordId:
                                property_exists(
                                    $existing,
                                    'id'
                                )
                                    ? $existing->id
                                    : null
                        );
                    }

                    $updatePayload = $payload;

                    unset(
                        $updatePayload['created_at']
                    );

                    DB::table($table)
                        ->where($identity)
                        ->update($updatePayload);

                    return new ErpPersistenceResult(
                        resource: $resource,

                        action:
                            ErpPersistenceAction::Updated,

                        table: $table,
                        externalId: $externalId,

                        recordId:
                            property_exists(
                                $existing,
                                'id'
                            )
                                ? $existing->id
                                : null
                    );
                },
                attempts: 3
            );
        } catch (ErpPersistenceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ErpPersistenceException
                ::databaseFailure(
                    resource: $resource,
                    table: $table,
                    externalId: $externalId,
                    previous: $exception
                );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mappedData(
        ErpMappedEntityInterface $entity,
        string $externalId
    ): array {
        $serialized = $entity->toArray();

        $data = $serialized['data']
            ?? null;

        if (! is_array($data)) {
            throw ErpPersistenceException
                ::invalidMappedData(
                    resource:
                        $entity->resource(),

                    externalId:
                        $externalId
                );
        }

        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[
                Str::snake((string) $key)
            ] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $targetColumns
     *
     * @return array<string, mixed>
     */
    private function resolveRelationships(
        ErpResource $resource,
        string $sourceSystem,
        string $externalId,
        array $data,
        array $targetColumns
    ): array {
        foreach (
            $this->registry
                ->relationships($resource)
            as $relationship
        ) {
            $targetColumn =
                $this->registry
                    ->firstExistingColumn(
                        $relationship[
                            'target_columns'
                        ],
                        $targetColumns
                    );

            if ($targetColumn === null) {
                continue;
            }

            $dependencyExternalId = null;

            foreach (
                $relationship['source_keys']
                as $sourceKey
            ) {
                $candidate =
                    $data[$sourceKey]
                    ?? null;

                if (
                    is_string($candidate)
                    || is_int($candidate)
                ) {
                    $candidate = trim(
                        (string) $candidate
                    );

                    if ($candidate !== '') {
                        $dependencyExternalId =
                            $candidate;

                        break;
                    }
                }
            }

            if ($dependencyExternalId === null) {
                if ($relationship['required']) {
                    throw ErpPersistenceException
                        ::missingDependency(
                            resource: $resource,
                            externalId: $externalId,

                            dependencyResource:
                                $relationship[
                                    'target_resource'
                                ]->value,

                            dependencyExternalId:
                                '[missing]'
                        );
                }

                continue;
            }

            $relatedId =
                $this->relatedRecordId(
                    sourceSystem:
                        $sourceSystem,

                    resource:
                        $relationship[
                            'target_resource'
                        ],

                    externalId:
                        $dependencyExternalId
                );

            if ($relatedId === null) {
                if ($relationship['required']) {
                    throw ErpPersistenceException
                        ::missingDependency(
                            resource: $resource,
                            externalId: $externalId,

                            dependencyResource:
                                $relationship[
                                    'target_resource'
                                ]->value,

                            dependencyExternalId:
                                $dependencyExternalId
                        );
                }

                continue;
            }

            $data[$targetColumn] =
                $relatedId;
        }

        return $data;
    }

    private function relatedRecordId(
        string $sourceSystem,
        ErpResource $resource,
        string $externalId
    ): int|string|null {
        $table =
            $this->registry
                ->tableFor($resource);

        $columns =
            $this->registry
                ->columnsFor($table);

        /*
         * Preferred ERP identity:
         * source_system + external_id.
         */
        if (
            in_array(
                'source_system',
                $columns,
                true
            )
            && in_array(
                'external_id',
                $columns,
                true
            )
        ) {
            $id = DB::table($table)
                ->where(
                    'source_system',
                    $sourceSystem
                )
                ->where(
                    'external_id',
                    $externalId
                )
                ->value('id');

            if ($id !== null) {
                return $id;
            }
        }

        /*
         * Compatibility for tables with external_id but without
         * source_system.
         */
        if (
            in_array(
                'external_id',
                $columns,
                true
            )
        ) {
            $id = DB::table($table)
                ->where(
                    'external_id',
                    $externalId
                )
                ->value('id');

            if ($id !== null) {
                return $id;
            }
        }

        /*
         * The Simulated Sage API may expose a local numeric
         * relationship ID instead of an ERP external ID.
         *
         * This fallback is intentionally restricted to the
         * simulated_sage source.
         */
        if (
            $sourceSystem === 'simulated_sage'
            && in_array(
                'id',
                $columns,
                true
            )
        ) {
            $localId = filter_var(
                $externalId,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

            if ($localId !== false) {
                $query = DB::table($table)
                    ->where(
                        'id',
                        $localId
                    );

                if (
                    in_array(
                        'source_system',
                        $columns,
                        true
                    )
                ) {
                    $query->where(
                        'source_system',
                        $sourceSystem
                    );
                }

                $id = $query->value('id');

                if ($id !== null) {
                    return $id;
                }
            }
        }

        /*
         * Final compatibility lookup for tables that use business
         * identifiers instead of external_id.
         */
        foreach (
            $this->registry
                ->businessKeys($resource)
            as $businessKey
        ) {
            if (
                ! in_array(
                    $businessKey,
                    $columns,
                    true
                )
            ) {
                continue;
            }

            $id = DB::table($table)
                ->where(
                    $businessKey,
                    $externalId
                )
                ->value('id');

            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function buildPayload(
        ErpResource $resource,
        array $data,
        array $columns,
        string $sourceSystem,
        string $externalId,
        ?int $sourceVersion,
        ?string $sourceChecksum,
        ?DateTimeInterface $sourceUpdatedAt
    ): array {
        $aliases =
            $this->registry
                ->aliases($resource);

        $payload = [];

        foreach ($data as $sourceKey => $value) {
            $column = in_array(
                $sourceKey,
                $columns,
                true
            )
                ? $sourceKey
                : $this->registry
                    ->firstExistingColumn(
                        $aliases[$sourceKey]
                            ?? [],
                        $columns
                    );

            if ($column === null) {
                continue;
            }

            $payload[$column] =
                $this->databaseValue(
                    $value
                );
        }

        $now = CarbonImmutable::now()
            ->utc()
            ->toDateTimeString();

        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'source_system',
            value: $sourceSystem
        );

        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'external_id',
            value: $externalId
        );

        if ($sourceVersion !== null) {
            $this->setWhenColumnExists(
                payload: $payload,
                columns: $columns,
                column: 'source_version',
                value: $sourceVersion
            );
        }

        if ($sourceChecksum !== null) {
            $this->setWhenColumnExists(
                payload: $payload,
                columns: $columns,
                column: 'source_checksum',
                value: $sourceChecksum
            );
        }

        if ($sourceUpdatedAt !== null) {
            $this->setWhenColumnExists(
                payload: $payload,
                columns: $columns,
                column: 'source_updated_at',

                value:
                    CarbonImmutable::instance(
                        $sourceUpdatedAt
                    )
                        ->utc()
                        ->toDateTimeString()
            );
        }

        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'last_synced_at',
            value: $now
        );

        /*
         * Records reaching this persister came through an ERP connector
         * and completed mapping and relationship validation.
         */
        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'import_status',
            value: 'imported'
        );

        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'import_error',
            value: null
        );

        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'created_at',
            value: $now
        );

        $this->setWhenColumnExists(
            payload: $payload,
            columns: $columns,
            column: 'updated_at',
            value: $now
        );

        unset($payload['id']);

        return $payload;
    }

    /**
     * @param list<string> $columns
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function identity(
        ErpResource $resource,
        string $table,
        array $columns,
        array $payload,
        string $sourceSystem,
        string $externalId
    ): array {
        if (
            in_array(
                'source_system',
                $columns,
                true
            )
            && in_array(
                'external_id',
                $columns,
                true
            )
        ) {
            return [
                'source_system' =>
                    $sourceSystem,

                'external_id' =>
                    $externalId,
            ];
        }

        if (
            in_array(
                'external_id',
                $columns,
                true
            )
        ) {
            return [
                'external_id' =>
                    $externalId,
            ];
        }

        foreach (
            $this->registry
                ->businessKeys($resource)
            as $businessKey
        ) {
            if (
                in_array(
                    $businessKey,
                    $columns,
                    true
                )
                && array_key_exists(
                    $businessKey,
                    $payload
                )
                && $payload[$businessKey]
                    !== null
                && $payload[$businessKey]
                    !== ''
            ) {
                return [
                    $businessKey =>
                        $payload[$businessKey],
                ];
            }
        }

        throw ErpPersistenceException
            ::missingIdentity(
                resource: $resource,
                table: $table,
                externalId: $externalId
            );
    }

    /**
     * Decide whether an incoming ERP record is already represented
     * by the current local database record.
     *
     * Synchronization metadata is evaluated separately from business
     * data so local fields such as last_synced_at cannot cause an
     * endless update loop.
     *
     * @param list<string> $columns
     * @param array<string, mixed> $payload
     */
    private function shouldSkip(
        object $existing,
        array $payload,
        array $columns
    ): bool {
        $incomingVersion =
            $this->integerValueOrNull(
                $payload['source_version']
                    ?? null
            );

        $existingVersion =
            $this->integerValueOrNull(
                property_exists(
                    $existing,
                    'source_version'
                )
                    ? $existing->source_version
                    : null
            );

        /*
         * Source version is authoritative whenever both records
         * provide one.
         */
        if (
            $incomingVersion !== null
            && $existingVersion !== null
        ) {
            if (
                $incomingVersion
                < $existingVersion
            ) {
                return true;
            }

            if (
                $incomingVersion
                > $existingVersion
            ) {
                return false;
            }
        }

        $incomingSourceUpdatedAt =
            $this->dateValueOrNull(
                $payload['source_updated_at']
                    ?? null
            );

        $existingSourceUpdatedAt =
            $this->dateValueOrNull(
                property_exists(
                    $existing,
                    'source_updated_at'
                )
                    ? $existing->source_updated_at
                    : null
            );

        /*
         * Never overwrite a record using an older source timestamp.
         */
        if (
            $incomingSourceUpdatedAt !== null
            && $existingSourceUpdatedAt !== null
            && $incomingSourceUpdatedAt
                ->lessThan(
                    $existingSourceUpdatedAt
                )
        ) {
            return true;
        }

        $incomingChecksum =
            $this->nonEmptyStringOrNull(
                $payload['source_checksum']
                    ?? null
            );

        $existingChecksum =
            $this->nonEmptyStringOrNull(
                property_exists(
                    $existing,
                    'source_checksum'
                )
                    ? $existing->source_checksum
                    : null
            );

        /*
         * Matching source checksums prove that the source content
         * has not changed.
         */
        if (
            $incomingChecksum !== null
            && $existingChecksum !== null
            && hash_equals(
                $existingChecksum,
                $incomingChecksum
            )
        ) {
            return true;
        }

        /*
         * A checksum difference by itself is not enough to perform
         * an update. The simulator response may include mutable local
         * metadata such as last_synced_at in its calculated checksum.
         *
         * Compare only stable business columns.
         */
        return $this->payloadMatchesExisting(
            existing: $existing,
            payload: $payload,
            columns: $columns
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $columns
     */
    private function payloadMatchesExisting(
        object $existing,
        array $payload,
        array $columns
    ): bool {
        $ignored = [
            'id',

            /*
             * Local database lifecycle metadata.
             */
            'created_at',
            'updated_at',
            'last_synced_at',

            /*
             * ERP synchronization identity and metadata.
             */
            'source_system',
            'external_id',
            'source_version',
            'source_checksum',
            'source_updated_at',
        ];

        foreach ($payload as $column => $value) {
            if (
                in_array(
                    $column,
                    $ignored,
                    true
                )
                || ! in_array(
                    $column,
                    $columns,
                    true
                )
            ) {
                continue;
            }

            $existingValue =
                property_exists(
                    $existing,
                    $column
                )
                    ? $existing->{$column}
                    : null;

            if (
                ! $this->valuesAreEquivalent(
                    $existingValue,
                    $value
                )
            ) {
                return false;
            }
        }

        return true;
    }

    private function valuesAreEquivalent(
        mixed $existing,
        mixed $incoming
    ): bool {
        if (
            $existing === null
            || $incoming === null
        ) {
            return $existing === null
                && $incoming === null;
        }

        if ($existing instanceof BackedEnum) {
            $existing = $existing->value;
        }

        if ($incoming instanceof BackedEnum) {
            $incoming = $incoming->value;
        }

        if ($existing instanceof DateTimeInterface) {
            $existing =
                CarbonImmutable::instance(
                    $existing
                )
                    ->utc()
                    ->toDateTimeString();
        }

        if ($incoming instanceof DateTimeInterface) {
            $incoming =
                CarbonImmutable::instance(
                    $incoming
                )
                    ->utc()
                    ->toDateTimeString();
        }

        /*
         * Normalize Boolean values returned by MySQL or SQLite as
         * 0/1, "0"/"1", true/false or "true"/"false".
         */
        $existingBoolean =
            $this->booleanValueOrNull(
                $existing
            );

        $incomingBoolean =
            $this->booleanValueOrNull(
                $incoming
            );

        if (
            is_bool($existing)
            || is_bool($incoming)
        ) {
            return $existingBoolean !== null
                && $incomingBoolean !== null
                && $existingBoolean
                    === $incomingBoolean;
        }

        /*
         * MySQL DECIMAL values are normally returned as strings,
         * for example "1.000", while mapped ERP values may be the
         * integer 1 or float 1.0.
         */
        if (
            $this->shouldCompareNumerically(
                $existing,
                $incoming
            )
        ) {
            return $this->normalizeNumericValue(
                $existing
            ) === $this->normalizeNumericValue(
                $incoming
            );
        }

        if (
            is_array($existing)
            || is_array($incoming)
        ) {
            return $this->canonicalJson(
                $existing
            ) === $this->canonicalJson(
                $incoming
            );
        }

        return trim((string) $existing)
            === trim((string) $incoming);
    }

    private function shouldCompareNumerically(
        mixed $existing,
        mixed $incoming
    ): bool {
        if (
            ! is_numeric($existing)
            || ! is_numeric($incoming)
        ) {
            return false;
        }

        /*
         * At least one native numeric value indicates a numeric
         * database column or mapped ERP value.
         */
        if (
            is_int($existing)
            || is_float($existing)
            || is_int($incoming)
            || is_float($incoming)
        ) {
            return true;
        }

        $existingString =
            trim((string) $existing);

        $incomingString =
            trim((string) $incoming);

        /*
         * Two integer-looking strings are compared literally so
         * business codes such as "001" are not treated as "1".
         */
        return str_contains(
            $existingString,
            '.'
        )
            || str_contains(
                $incomingString,
                '.'
            )
            || stripos(
                $existingString,
                'e'
            ) !== false
            || stripos(
                $incomingString,
                'e'
            ) !== false;
    }

    private function normalizeNumericValue(
        mixed $value
    ): string {
        $value = trim(
            (string) $value
        );

        if (
            preg_match(
                '/^[+-]?\d+(?:\.\d+)?$/',
                $value
            ) === 1
        ) {
            $negative =
                str_starts_with(
                    $value,
                    '-'
                );

            $value = ltrim(
                $value,
                '+-'
            );

            [
                $integer,
                $fraction,
            ] = array_pad(
                explode(
                    '.',
                    $value,
                    2
                ),
                2,
                ''
            );

            $integer = ltrim(
                $integer,
                '0'
            );

            if ($integer === '') {
                $integer = '0';
            }

            $fraction = rtrim(
                $fraction,
                '0'
            );

            $normalized =
                $fraction === ''
                    ? $integer
                    : $integer
                        .'.'
                        .$fraction;

            if ($normalized === '0') {
                return '0';
            }

            return $negative
                ? '-'.$normalized
                : $normalized;
        }

        /*
         * Scientific notation is normalized through a fixed decimal
         * representation.
         */
        $formatted = number_format(
            (float) $value,
            14,
            '.',
            ''
        );

        return $this->normalizeNumericValue(
            $formatted
        );
    }

    private function booleanValueOrNull(
        mixed $value
    ): ?bool {
        if (is_bool($value)) {
            return $value;
        }

        if (
            $value === 1
            || $value === '1'
        ) {
            return true;
        }

        if (
            $value === 0
            || $value === '0'
        ) {
            return false;
        }

        if (is_string($value)) {
            return match (
                strtolower(trim($value))
            ) {
                'true', 'yes', 'on' => true,
                'false', 'no', 'off' => false,
                default => null,
            };
        }

        return null;
    }

    private function integerValueOrNull(
        mixed $value
    ): ?int {
        if (is_int($value)) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^\d+$/',
                trim($value)
            ) === 1
        ) {
            return (int) trim($value);
        }

        if (
            is_float($value)
            && is_finite($value)
            && floor($value) === $value
        ) {
            return (int) $value;
        }

        return null;
    }

    private function dateValueOrNull(
        mixed $value
    ): ?CarbonImmutable {
        if (
            $value === null
            || (
                is_string($value)
                && trim($value) === ''
            )
        ) {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance(
                    $value
                )->utc();
            }

            return CarbonImmutable::parse(
                (string) $value
            )->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function nonEmptyStringOrNull(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function canonicalJson(
        mixed $value
    ): string {
        if (is_string($value)) {
            try {
                $decoded = json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $value = $decoded;
            } catch (JsonException) {
                return trim($value);
            }
        }

        if (! is_array($value)) {
            return trim((string) $value);
        }

        $value =
            $this->sortArrayRecursively(
                $value
            );

        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException) {
            return '';
        }
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private function sortArrayRecursively(
        array $value
    ): array {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] =
                    $this->sortArrayRecursively(
                        $item
                    );
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $identity
     * @param list<string> $columns
     */
    private function touchSkippedRecord(
        string $table,
        array $identity,
        array $columns
    ): void {
        if (
            ! in_array(
                'last_synced_at',
                $columns,
                true
            )
        ) {
            return;
        }

        DB::table($table)
            ->where($identity)
            ->update([
                'last_synced_at' =>
                    CarbonImmutable::now()
                        ->utc()
                        ->toDateTimeString(),
            ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $columns
     */
    private function setWhenColumnExists(
        array &$payload,
        array $columns,
        string $column,
        mixed $value
    ): void {
        if (
            in_array(
                $column,
                $columns,
                true
            )
        ) {
            $payload[$column] = $value;
        }
    }

    private function databaseValue(
        mixed $value
    ): mixed {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(
                $value
            )
                ->utc()
                ->toDateTimeString();
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            try {
                return json_encode(
                    $value,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );
            } catch (JsonException) {
                return null;
            }
        }

        if (is_object($value)) {
            return get_debug_type($value);
        }

        return $value;
    }
}
