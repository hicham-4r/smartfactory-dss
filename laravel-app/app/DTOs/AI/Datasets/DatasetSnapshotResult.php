<?php

namespace App\DTOs\AI\Datasets;

use JsonSerializable;

final readonly class DatasetSnapshotResult implements
    JsonSerializable
{
    /**
     * @param list<DatasetFileManifest> $files
     */
    public function __construct(
        public string $snapshotId,
        public string $snapshotDirectory,
        public string $manifestPath,
        public string $contentFingerprint,
        public int $totalRows,
        public array $files,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'snapshot_id' =>
                $this->snapshotId,
            'snapshot_directory' =>
                $this->snapshotDirectory,
            'manifest_path' =>
                $this->manifestPath,
            'content_fingerprint' =>
                $this->contentFingerprint,
            'total_rows' =>
                $this->totalRows,
            'datasets' =>
                array_map(
                    static fn (
                        DatasetFileManifest $file
                    ): array =>
                        $file->toArray(),
                    $this->files
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
