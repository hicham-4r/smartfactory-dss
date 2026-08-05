<?php

namespace App\DTOs\AI\Datasets;

use App\Enums\AI\DatasetType;
use JsonSerializable;

final readonly class DatasetFileManifest implements
    JsonSerializable
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public DatasetType $dataset,
        public string $relativePath,
        public string $schemaVersion,
        public int $rowCount,
        public int $byteSize,
        public string $sha256,
        public array $columns,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' =>
                $this->dataset->value,
            'file' =>
                $this->relativePath,
            'schema_version' =>
                $this->schemaVersion,
            'row_count' =>
                $this->rowCount,
            'byte_size' =>
                $this->byteSize,
            'sha256' =>
                $this->sha256,
            'columns' =>
                $this->columns,
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
