<?php

namespace App\Contracts\ERP;

use App\DTOs\ERP\ErpConnectorHealth;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\Enums\ERP\ErpResource;

interface ErpConnectorInterface
{
    public function name(): string;

    public function sourceSystem(): string;

    public function supports(
        ErpResource $resource
    ): bool;

    public function health(): ErpConnectorHealth;

    public function fetchPage(
        ErpResource $resource,
        ErpPageRequest $request
    ): ErpPage;
}