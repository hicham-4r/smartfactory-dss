<?php

namespace Tests\Fakes;

use App\Contracts\ERP\ErpConnectorInterface;
use App\DTOs\ERP\ErpConnectorHealth;
use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\ErpSourceRecord;
use App\Enums\ERP\ErpConnectorHealthStatus;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpConfigurationException;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class FakeErpConnector implements ErpConnectorInterface
{
    /**
     * @var array<string, list<ErpSourceRecord>>
     */
    private array $records = [];

    /**
     * @var list<array{
     *     resource: ErpResource,
     *     request: ErpPageRequest
     * }>
     */
    private array $requests = [];

    public function __construct(
        private readonly string $sourceSystem =
            'fake_erp'
    ) {
    }

    /**
     * @param list<ErpSourceRecord> $records
     */
    public function seed(
        ErpResource $resource,
        array $records
    ): self {
        foreach ($records as $record) {
            if (
                ! $record
                instanceof ErpSourceRecord
                || $record->identity->resource
                    !== $resource
            ) {
                throw new InvalidArgumentException(
                    'Fake ERP records must match the seeded resource.'
                );
            }
        }

        $this->records[$resource->value] =
            array_values($records);

        return $this;
    }

    public function name(): string
    {
        return 'Fake ERP connector';
    }

    public function sourceSystem(): string
    {
        return $this->sourceSystem;
    }

    public function supports(
        ErpResource $resource
    ): bool {
        return array_key_exists(
            $resource->value,
            $this->records
        );
    }

    public function health(): ErpConnectorHealth
    {
        return new ErpConnectorHealth(
            status:
                ErpConnectorHealthStatus
                    ::Healthy,

            checkedAt:
                CarbonImmutable::now(),

            latencyMilliseconds: 0,

            message:
                'Fake connector is available.'
        );
    }

    public function fetchPage(
        ErpResource $resource,
        ErpPageRequest $request
    ): ErpPage {
        if (! $this->supports($resource)) {
            throw ErpConfigurationException
                ::unsupportedResource(
                    $resource
                );
        }

        $this->requests[] = [
            'resource' => $resource,
            'request' => $request,
        ];

        $records =
            $this->records[$resource->value];

        $updatedSince =
            $request->cursor?->updatedSince;

        $sourceVersion =
            $request->cursor?->sourceVersion;

        $records = array_values(
            array_filter(
                $records,
                function (
                    ErpSourceRecord $record
                ) use (
                    $updatedSince,
                    $sourceVersion,
                    $request
                ): bool {
                    if (
                        $updatedSince !== null
                        && (
                            $record->sourceUpdatedAt
                                === null
                            || $record
                                ->sourceUpdatedAt
                                ->lessThanOrEqualTo(
                                    $updatedSince
                                )
                        )
                    ) {
                        return false;
                    }

                    if (
                        $sourceVersion !== null
                        && (
                            $record->sourceVersion
                                === null
                            || $record
                                ->sourceVersion
                                <= $sourceVersion
                        )
                    ) {
                        return false;
                    }

                    foreach (
                        $request->filters
                        as $key => $value
                    ) {
                        if (
                            (
                                $record
                                    ->attributes[$key]
                                ?? null
                            ) !== $value
                        ) {
                            return false;
                        }
                    }

                    return true;
                }
            )
        );

        $total = count($records);

        $offset =
            ($request->page - 1)
            * $request->perPage;

        $pageRecords = array_slice(
            $records,
            $offset,
            $request->perPage
        );

        $nextPage =
            $offset + count($pageRecords)
            < $total
                ? $request->page + 1
                : null;

        return new ErpPage(
            resource: $resource,
            records: $pageRecords,
            currentPage: $request->page,
            perPage: $request->perPage,
            total: $total,
            nextPage: $nextPage,

            nextCursor:
                $nextPage === null
                    ? null
                    : 'fake-page-'.$nextPage,

            fetchedAt:
                CarbonImmutable::now(),

            responseId:
                'fake-'
                .bin2hex(
                    random_bytes(8)
                )
        );
    }

    /**
     * @return list<array{
     *     resource: ErpResource,
     *     request: ErpPageRequest
     * }>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}