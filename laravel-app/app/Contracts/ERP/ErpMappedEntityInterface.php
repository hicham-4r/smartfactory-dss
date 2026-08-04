<?php

namespace App\Contracts\ERP;

use App\DTOs\ERP\ErpSourceRecord;
use App\Enums\ERP\ErpResource;

interface ErpMappedEntityInterface
{
    public function source(): ErpSourceRecord;

    public function resource(): ErpResource;

    /**
     * Return normalized data together with its ERP source metadata.
     *
     * @return array{
     *     source: array<string, mixed>,
     *     data: array<string, mixed>
     * }
     */
    public function toArray(): array;
}