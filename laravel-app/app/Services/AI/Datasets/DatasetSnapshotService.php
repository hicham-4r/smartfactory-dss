<?php

namespace App\Services\AI\Datasets;

use App\Contracts\AI\Datasets\DatasetSnapshotRepositoryInterface;
use App\DTOs\AI\Datasets\DatasetFileManifest;
use App\DTOs\AI\Datasets\DatasetSnapshotRequest;
use App\DTOs\AI\Datasets\DatasetSnapshotResult;
use App\Enums\AI\DatasetType;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

final class DatasetSnapshotService
{
    public function __construct(
        private readonly DatasetSnapshotRepositoryInterface
            $repository,
        private readonly DatasetSchemaRegistry
            $schemas,
        private readonly DatasetRootGuard
            $rootGuard,
        private readonly AuditLogService
            $audit,
    ) {
    }

    public function create(
        DatasetSnapshotRequest $request
    ): DatasetSnapshotResult {
        $root = $this->rootGuard
            ->normalize(
                config(
                    'ai-datasets.root'
                )
            );

        $this->prepareRoot($root);

        $lockHandle = $this->lock(
            $root
        );

        $snapshotId =
            (string) Str::uuid();

        $stagingDirectory =
            $root
            .DIRECTORY_SEPARATOR
            .'.staging'
            .DIRECTORY_SEPARATOR
            .$snapshotId;

        $finalDirectory =
            $root
            .DIRECTORY_SEPARATOR
            .'snapshots'
            .DIRECTORY_SEPARATOR
            .$snapshotId;

        try {
            $this->prepareStaging(
                $stagingDirectory
            );

            $files = [];

            foreach (
                $request->datasets
                as $dataset
            ) {
                $files[] =
                    $this->writeDataset(
                        stagingDirectory:
                            $stagingDirectory,
                        dataset: $dataset,
                        request: $request
                    );
            }

            $manifest = $this->manifest(
                snapshotId:
                    $snapshotId,
                request:
                    $request,
                files:
                    $files
            );

            $manifestPath =
                $stagingDirectory
                .DIRECTORY_SEPARATOR
                .'manifest.json';

            $this->writeJson(
                $manifestPath,
                $manifest
            );

            $manifestHash =
                hash_file(
                    'sha256',
                    $manifestPath
                );

            if (! is_string($manifestHash)) {
                throw new RuntimeException(
                    'The dataset manifest checksum could not be calculated.'
                );
            }

            $this->writeAtomic(
                $stagingDirectory
                    .DIRECTORY_SEPARATOR
                    .'manifest.sha256',
                $manifestHash
                    .'  manifest.json'
                    ."\n"
            );

            if (
                File::exists(
                    $finalDirectory
                )
            ) {
                throw new RuntimeException(
                    'The dataset snapshot identifier already exists.'
                );
            }

            File::ensureDirectoryExists(
                dirname($finalDirectory),
                0700,
                true
            );

            if (
                ! @rename(
                    $stagingDirectory,
                    $finalDirectory
                )
            ) {
                throw new RuntimeException(
                    'The dataset snapshot could not be published atomically.'
                );
            }

            $this->writeAtomic(
                $root
                    .DIRECTORY_SEPARATOR
                    .'LATEST',
                $snapshotId."\n"
            );

            $totalRows = array_sum(
                array_map(
                    static fn (
                        DatasetFileManifest $file
                    ): int =>
                        $file->rowCount,
                    $files
                )
            );

            $result =
                new DatasetSnapshotResult(
                    snapshotId:
                        $snapshotId,
                    snapshotDirectory:
                        $finalDirectory,
                    manifestPath:
                        $finalDirectory
                        .DIRECTORY_SEPARATOR
                        .'manifest.json',
                    contentFingerprint:
                        (string) $manifest[
                            'content_fingerprint'
                        ],
                    totalRows:
                        $totalRows,
                    files:
                        $files
                );

            $this->recordAudit(
                $request,
                $result
            );

            return $result;
        } catch (Throwable $exception) {
            if (
                File::isDirectory(
                    $stagingDirectory
                )
            ) {
                File::deleteDirectory(
                    $stagingDirectory
                );
            }

            throw $exception;
        } finally {
            $this->unlock(
                $lockHandle
            );
        }
    }

    private function prepareRoot(
        string $root
    ): void {
        File::ensureDirectoryExists(
            $root,
            0700,
            true
        );

        File::ensureDirectoryExists(
            $root
                .DIRECTORY_SEPARATOR
                .'.staging',
            0700,
            true
        );

        File::ensureDirectoryExists(
            $root
                .DIRECTORY_SEPARATOR
                .'snapshots',
            0700,
            true
        );

        if (! is_writable($root)) {
            throw new RuntimeException(
                'The configured AI dataset root is not writable.'
            );
        }
    }

    /**
     * @return resource
     */
    private function lock(
        string $root
    ) {
        $path =
            $root
            .DIRECTORY_SEPARATOR
            .'.snapshot.lock';

        $handle = @fopen(
            $path,
            'c+b'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'The dataset snapshot lock could not be opened.'
            );
        }

        if (
            ! flock(
                $handle,
                LOCK_EX | LOCK_NB
            )
        ) {
            fclose($handle);

            throw new RuntimeException(
                'Another dataset snapshot is already being generated.'
            );
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function unlock(
        $handle
    ): void {
        flock(
            $handle,
            LOCK_UN
        );

        fclose($handle);
    }

    private function prepareStaging(
        string $stagingDirectory
    ): void {
        if (
            File::exists(
                $stagingDirectory
            )
        ) {
            throw new RuntimeException(
                'The dataset staging directory already exists.'
            );
        }

        File::ensureDirectoryExists(
            $stagingDirectory
                .DIRECTORY_SEPARATOR
                .'data',
            0700,
            true
        );
    }

    private function writeDataset(
        string $stagingDirectory,
        DatasetType $dataset,
        DatasetSnapshotRequest $request
    ): DatasetFileManifest {
        $columns =
            $this->schemas
                ->columns($dataset);

        $relativePath =
            'data/'
            .$dataset->filename();

        $absolutePath =
            $stagingDirectory
            .DIRECTORY_SEPARATOR
            .'data'
            .DIRECTORY_SEPARATOR
            .$dataset->filename();

        $handle = @fopen(
            $absolutePath,
            'x+b'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'The dataset file could not be created.'
            );
        }

        $rowCount = 0;
        $maximumRows = max(
            1,
            (int) config(
                'ai-datasets.maximum_rows_per_file',
                1000000
            )
        );

        $maximumBytes = max(
            1024,
            (int) config(
                'ai-datasets.maximum_bytes_per_file',
                536870912
            )
        );

        try {
            $this->writeCsvRow(
                $handle,
                $columns
            );

            foreach (
                $this->repository
                    ->rows(
                        $dataset,
                        $request
                    )
                as $row
            ) {
                if (
                    array_keys($row)
                    !== $columns
                ) {
                    throw new RuntimeException(
                        'A dataset row did not match the registered schema.'
                    );
                }

                $rowCount++;

                if (
                    $rowCount
                    > $maximumRows
                ) {
                    throw new RuntimeException(
                        'A dataset exceeded the configured row limit.'
                    );
                }

                $this->writeCsvRow(
                    $handle,
                    array_values($row)
                );

                $position = ftell(
                    $handle
                );

                if (
                    $position !== false
                    && $position
                        > $maximumBytes
                ) {
                    throw new RuntimeException(
                        'A dataset exceeded the configured file-size limit.'
                    );
                }
            }

            if (! fflush($handle)) {
                throw new RuntimeException(
                    'The dataset file could not be flushed safely.'
                );
            }
        } finally {
            fclose($handle);
        }

        $byteSize = filesize(
            $absolutePath
        );

        $sha256 = hash_file(
            'sha256',
            $absolutePath
        );

        if (
            $byteSize === false
            || ! is_string($sha256)
        ) {
            throw new RuntimeException(
                'The dataset file metadata could not be calculated.'
            );
        }

        return new DatasetFileManifest(
            dataset:
                $dataset,
            relativePath:
                $relativePath,
            schemaVersion:
                DatasetSchemaRegistry
                    ::SCHEMA_VERSION,
            rowCount:
                $rowCount,
            byteSize:
                $byteSize,
            sha256:
                $sha256,
            columns:
                $columns
        );
    }

    /**
     * @param resource $handle
     * @param list<int|string|null> $values
     */
    private function writeCsvRow(
        $handle,
        array $values
    ): void {
        $safeValues = array_map(
            fn (
                int|string|null $value
            ): int|string =>
                $this->safeCsvValue(
                    $value
                ),
            $values
        );

        $written = fputcsv(
            $handle,
            $safeValues,
            ',',
            '"',
            '',
            "\n"
        );

        if ($written === false) {
            throw new RuntimeException(
                'A dataset CSV row could not be written.'
            );
        }
    }

    private function safeCsvValue(
        int|string|null $value
    ): int|string {
        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return $value;
        }

        $normalized = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value
        );

        if (! is_string($normalized)) {
            throw new RuntimeException(
                'A dataset value could not be sanitized.'
            );
        }

        $normalized = str_replace(
            [
                "\r\n",
                "\r",
                "\n",
            ],
            ' ',
            $normalized
        );

        if (
            $normalized !== ''
            && ! is_numeric($normalized)
            && in_array(
                $normalized[0],
                [
                    '=',
                    '+',
                    '-',
                    '@',
                ],
                true
            )
        ) {
            return "'".$normalized;
        }

        return $normalized;
    }

    /**
     * @param list<DatasetFileManifest> $files
     *
     * @return array<string, mixed>
     */
    private function manifest(
        string $snapshotId,
        DatasetSnapshotRequest $request,
        array $files
    ): array {
        $generatedAt =
            CarbonImmutable::now()
                ->utc();

        $fileArrays = array_map(
            static fn (
                DatasetFileManifest $file
            ): array =>
                $file->toArray(),
            $files
        );

        $totalRows = array_sum(
            array_column(
                $fileArrays,
                'row_count'
            )
        );

        $manifest = [
            'manifest_version' =>
                DatasetSchemaRegistry
                    ::MANIFEST_VERSION,
            'dataset_contract' =>
                DatasetSchemaRegistry
                    ::CONTRACT_NAME,
            'dataset_schema_version' =>
                DatasetSchemaRegistry
                    ::SCHEMA_VERSION,
            'snapshot_id' =>
                $snapshotId,
            'source_application' =>
                DatasetSchemaRegistry
                    ::SOURCE_APPLICATION,
            'source_system' =>
                $request->sourceSystem,
            'data_classification' =>
                DatasetSchemaRegistry
                    ::DATA_CLASSIFICATION,
            'generated_at' =>
                $generatedAt
                    ->toIso8601String(),
            'period' => [
                'start_date' =>
                    $request
                        ->startDateString(),
                'end_date' =>
                    $request
                        ->endDateString(),
                'timezone' =>
                    $request->timezone,
                'utc_start' =>
                    $request
                        ->utcStart()
                        ->toIso8601String(),
                'utc_end_exclusive' =>
                    $request
                        ->utcEndExclusive()
                        ->toIso8601String(),
            ],
            'generator' => [
                'name' =>
                    'smartfactory-dss-laravel',
                'version' =>
                    '0.1.0',
            ],
            'total_rows' =>
                $totalRows,
            'datasets' =>
                $fileArrays,
        ];

        $manifest['content_fingerprint'] =
            $this->fingerprint(
                $manifest
            );

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function fingerprint(
        array $manifest
    ): string {
        $lines = [
            (string) $manifest[
                'dataset_contract'
            ],
            (string) $manifest[
                'dataset_schema_version'
            ],
            (string) $manifest[
                'source_system'
            ],
            (string) $manifest[
                'data_classification'
            ],
            (string) $manifest[
                'period'
            ]['start_date'],
            (string) $manifest[
                'period'
            ]['end_date'],
            (string) $manifest[
                'period'
            ]['timezone'],
        ];

        $datasets =
            $manifest['datasets'];

        usort(
            $datasets,
            static fn (
                array $left,
                array $right
            ): int =>
                strcmp(
                    (string) $left['name'],
                    (string) $right['name']
                )
        );

        foreach ($datasets as $dataset) {
            $lines[] =
                $dataset['name']
                .'|'
                .$dataset['sha256']
                .'|'
                .$dataset['row_count']
                .'|'
                .$dataset['byte_size'];
        }

        return hash(
            'sha256',
            implode(
                "\n",
                $lines
            )
        );
    }

    /**
     * @param array<string, mixed> $value
     */
    private function writeJson(
        string $path,
        array $value
    ): void {
        try {
            $json = json_encode(
                $value,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'The dataset manifest could not be encoded safely.',
                previous:
                    $exception
            );
        }

        $this->writeAtomic(
            $path,
            $json."\n"
        );
    }

    private function writeAtomic(
        string $path,
        string $contents
    ): void {
        $temporaryPath =
            $path
            .'.tmp-'
            .bin2hex(
                random_bytes(8)
            );

        $bytes = @file_put_contents(
            $temporaryPath,
            $contents,
            LOCK_EX
        );

        if (
            $bytes === false
            || $bytes !== strlen($contents)
        ) {
            @unlink(
                $temporaryPath
            );

            throw new RuntimeException(
                'A dataset metadata file could not be written safely.'
            );
        }

        if (! @rename(
            $temporaryPath,
            $path
        )) {
            @unlink(
                $temporaryPath
            );

            throw new RuntimeException(
                'A dataset metadata file could not be published atomically.'
            );
        }
    }

    private function recordAudit(
        DatasetSnapshotRequest $request,
        DatasetSnapshotResult $result
    ): void {
        if (
            ! (bool) config(
                'ai-datasets.audit_enabled',
                true
            )
        ) {
            return;
        }

        try {
            $this->audit->record(
                action:
                    'ai.dataset_snapshot.created',
                metadata: [
                    'snapshot_id' =>
                        $result->snapshotId,
                    'content_fingerprint' =>
                        $result
                            ->contentFingerprint,
                    'total_rows' =>
                        $result->totalRows,
                    'datasets' =>
                        array_map(
                            static fn (
                                DatasetFileManifest $file
                            ): array => [
                                'name' =>
                                    $file
                                        ->dataset
                                        ->value,
                                'row_count' =>
                                    $file
                                        ->rowCount,
                                'sha256' =>
                                    $file
                                        ->sha256,
                            ],
                            $result->files
                        ),
                    'period' => [
                        'start_date' =>
                            $request
                                ->startDateString(),
                        'end_date' =>
                            $request
                                ->endDateString(),
                        'timezone' =>
                            $request
                                ->timezone,
                    ],
                    'data_classification' =>
                        DatasetSchemaRegistry
                            ::DATA_CLASSIFICATION,
                ]
            );
        } catch (Throwable $exception) {
            /*
             * The snapshot is already atomically published. Audit failure is
             * reported without exposing dataset values or filesystem paths.
             */
            Log::warning(
                'AI dataset snapshot audit recording failed safely.',
                [
                    'snapshot_id' =>
                        $result->snapshotId,
                    'exception_type' =>
                        $exception::class,
                ]
            );
        }
    }
}
