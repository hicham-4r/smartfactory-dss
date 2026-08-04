<?php

namespace App\Services\ERP;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpConnectorHealth;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpConfigurationException;
use Carbon\CarbonImmutable;

final class DisabledErpConnector implements ErpConnectorInterface
{
    public function name(): string
    {
        return 'Disabled ERP connector';
    }

    public function sourceSystem(): string
    {
        return 'disabled';
    }

    public function supports(
        ErpResource $resource
    ): bool {
        return false;
    }

    public function health(): ErpConnectorHealth
    {
        return new ErpConnectorHealth(
            status:
                ErpConnectorHealthStatus
                    ::Disabled,

            checkedAt:
                CarbonImmutable::now(),

            latencyMilliseconds: null,

            message:
                'ERP integration is disabled.'
        );
    }

    public function fetchPage(
        ErpResource $resource,
        ErpPageRequest $request
    ): ErpPage {
        throw ErpConfigurationException
            ::disabled();
    }
}