<?php

namespace App\Contracts\ERP;

use App\DTOs\ERP\ErpSourceRecord;

interface ErpRecordMapperInterface
{
    public function name(): string;

    public function sourceSystem(): string;

    public function supports(
        ErpSourceRecord $record
    ): bool;

    public function map(
        ErpSourceRecord $record
    ): ErpMappedEntityInterface;
}