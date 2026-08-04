<?php

namespace App\DTOs\ERP\Mapped\Concerns;

use App\DTOs\ERP\ErpSourceRecord;
use App\Enums\ERP\ErpResource;

trait SerializesErpMappedEntity
{
    public function source(): ErpSourceRecord
    {
        return $this->source;
    }

    public function resource(): ErpResource
    {
        return $this->source
            ->identity
            ->resource;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     source: array<string, mixed>,
     *     data: array<string, mixed>
     * }
     */
    protected function envelope(
        array $data
    ): array {
        return [
            'source' =>
                $this->source->toArray(),

            'data' => $data,
        ];
    }
}