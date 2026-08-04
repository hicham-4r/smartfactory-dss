<?php

namespace App\Services\ERP;

use App\DTOs\ERP\ErpPage;
use App\DTOs\ERP\ErpPageRequest;
use App\DTOs\ERP\ErpSourceIdentity;
use App\DTOs\ERP\ErpSourceRecord;
use App\DTOs\ERP\SimulatedSageConnectorConfig;
use App\Enums\ERP\ErpResource;
use App\Exceptions\ERP\ErpInvalidResponseException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use JsonException;
use Throwable;

final class SimulatedSageResponseNormalizer
{
    public function __construct(
        private readonly SimulatedSageConnectorConfig $config
    ) {
    }

    public function normalizePage(
        ErpResource $resource,
        ErpPageRequest $request,
        Response $response
    ): ErpPage {
        $body = $response->body();

        if (
            strlen($body)
            > $this->config
                ->maximumResponseBytes
        ) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    'the response exceeds the configured size limit',

                safeContext: [
                    'status_code' =>
                        $response->status(),

                    'body_bytes' =>
                        strlen($body),

                    'maximum_body_bytes' =>
                        $this->config
                            ->maximumResponseBytes,
                ]
            );
        }

        try {
            $payload = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    'the body is not valid JSON',

                safeContext: [
                    'status_code' =>
                        $response->status(),

                    'body_bytes' =>
                        strlen($body),
                ]
            );
        }

        if (! is_array($payload)) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    'the JSON root must be an object or list',

                safeContext: [
                    'status_code' =>
                        $response->status(),
                ]
            );
        }

        [$items, $metadata] =
            $this->extractItemsAndMetadata(
                $resource,
                $payload
            );

        $records = [];

        foreach ($items as $index => $item) {
            if (
                ! is_array($item)
                || (
                    $item !== []
                    && array_is_list($item)
                )
            ) {
                throw ErpInvalidResponseException::forResource(
                    resource: $resource,

                    reason:
                        'a record is not a JSON object',

                    safeContext: [
                        'record_index' => $index,
                    ]
                );
            }

            $records[] = $this->normalizeRecord(
                $resource,
                $item,
                $index
            );
        }

        $currentPage = $this->integer(
            resource: $resource,

            value:
                $metadata['current_page']
                ?? $request->page,

            field: 'current_page',
            minimum: 1
        );

        $perPage = $this->integer(
            resource: $resource,

            value:
                $metadata['per_page']
                ?? $request->perPage,

            field: 'per_page',
            minimum: 1,
            maximum:
                $this->config
                    ->maximumPageSize
        );

        $total = $this->optionalInteger(
            resource: $resource,

            value:
                $metadata['total']
                ?? null,

            field: 'total',
            minimum: 0
        );

        $nextPage = $this->extractNextPage(
            resource: $resource,
            metadata: $metadata,
            currentPage: $currentPage
        );

        $nextCursor = $this->optionalString(
            $metadata['next_cursor']
                ?? $metadata['cursor_next']
                ?? null
        );

        $responseId =
            $this->optionalString(
                $response->header(
                    'X-Request-ID'
                )
            )
            ?? $this->optionalString(
                $response->header(
                    'X-Correlation-ID'
                )
            )
            ?? $this->optionalString(
                $metadata['request_id']
                    ?? null
            );

        return new ErpPage(
            resource: $resource,
            records: $records,
            currentPage: $currentPage,
            perPage: $perPage,
            total: $total,
            nextPage: $nextPage,
            nextCursor: $nextCursor,

            fetchedAt:
                CarbonImmutable::now(),

            responseId:
                $responseId
        );
    }

    /**
     * @param array<string|int, mixed> $payload
     *
     * @return array{
     *     0: list<array<string, mixed>>,
     *     1: array<string, mixed>
     * }
     */
    private function extractItemsAndMetadata(
        ErpResource $resource,
        array $payload
    ): array {
        if (array_is_list($payload)) {
            return [
                $payload,
                [],
            ];
        }

        $items =
            $payload['data']
            ?? $payload['items']
            ?? $payload['records']
            ?? null;

        if (! is_array($items)) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    'the response does not contain a data list'
            );
        }

        $metadata = [];

        if (
            isset($payload['meta'])
            && is_array($payload['meta'])
        ) {
            $metadata = $payload['meta'];
        }

        foreach (
            [
                'current_page',
                'per_page',
                'total',
                'last_page',
                'next_page',
                'next_page_url',
                'next_cursor',
                'cursor_next',
                'request_id',
            ] as $key
        ) {
            if (array_key_exists($key, $payload)) {
                $metadata[$key] =
                    $payload[$key];
            }
        }

        if (
            isset($payload['links'])
            && is_array($payload['links'])
        ) {
            $links = $payload['links'];

            if (
                ! array_key_exists(
                    'next_page_url',
                    $metadata
                )
            ) {
                $metadata['next_page_url'] =
                    $links['next']
                    ?? null;
            }

            if (
                ! array_key_exists(
                    'next_cursor',
                    $metadata
                )
            ) {
                $metadata['next_cursor'] =
                    $links['next_cursor']
                    ?? null;
            }
        }

        return [
            array_values($items),
            $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function normalizeRecord(
        ErpResource $resource,
        array $item,
        int $index
    ): ErpSourceRecord {
        $externalId =
            $item['external_id']
            ?? $item['id']
            ?? $item['uuid']
            ?? $item['code']
            ?? $item['reference']
            ?? null;

        if (
            ! is_string($externalId)
            && ! is_int($externalId)
        ) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    'a record has no usable external identifier',

                safeContext: [
                    'record_index' => $index,
                ]
            );
        }

        $externalId = trim(
            (string) $externalId
        );

        if (
            $externalId === ''
            || mb_strlen($externalId) > 120
        ) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    'a record external identifier is invalid',

                safeContext: [
                    'record_index' => $index,
                ]
            );
        }

        $sourceVersion =
            $this->optionalInteger(
                resource: $resource,

                value:
                    $item['source_version']
                    ?? $item['version']
                    ?? $item['lock_version']
                    ?? null,

                field: 'source_version',
                minimum: 0,

                safeContext: [
                    'record_index' => $index,
                ]
            );

        $sourceUpdatedAt =
            $this->optionalDateTime(
                resource: $resource,

                value:
                    $item['source_updated_at']
                    ?? $item['updated_at']
                    ?? null,

                field: 'source_updated_at',

                safeContext: [
                    'record_index' => $index,
                ]
            );

        $attributes = $item;

        unset(
            $attributes['external_id'],
            $attributes['id'],
            $attributes['uuid'],
            $attributes['source_version'],
            $attributes['version'],
            $attributes['lock_version'],
            $attributes['source_updated_at']
        );

        if (
            array_key_exists(
                'attributes',
                $attributes
            )
        ) {
            if (
                ! is_array(
                    $attributes['attributes']
                )
            ) {
                throw ErpInvalidResponseException::forResource(
                    resource: $resource,

                    reason:
                        'the nested attributes value is not an object',

                    safeContext: [
                        'record_index' => $index,
                    ]
                );
            }

            $nestedAttributes =
                $attributes['attributes'];

            unset($attributes['attributes']);

            $attributes = array_replace(
                $attributes,
                $nestedAttributes
            );
        }

        return new ErpSourceRecord(
            identity:
                new ErpSourceIdentity(
                    sourceSystem:
                        $this->config
                            ->sourceSystem,

                    resource:
                        $resource,

                    externalId:
                        $externalId
                ),

            attributes:
                $attributes,

            sourceVersion:
                $sourceVersion,

            sourceUpdatedAt:
                $sourceUpdatedAt,

            receivedAt:
                CarbonImmutable::now()
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function extractNextPage(
        ErpResource $resource,
        array $metadata,
        int $currentPage
    ): ?int {
        if (
            array_key_exists(
                'next_page',
                $metadata
            )
            && $metadata['next_page'] !== null
        ) {
            return $this->integer(
                resource: $resource,
                value: $metadata['next_page'],
                field: 'next_page',
                minimum: $currentPage + 1
            );
        }

        if (
            ! empty(
                $metadata['next_page_url']
            )
        ) {
            return $currentPage + 1;
        }

        $lastPage = $this->optionalInteger(
            resource: $resource,

            value:
                $metadata['last_page']
                ?? null,

            field: 'last_page',
            minimum: 1
        );

        if (
            $lastPage !== null
            && $currentPage < $lastPage
        ) {
            return $currentPage + 1;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $safeContext
     */
    private function optionalInteger(
        ErpResource $resource,
        mixed $value,
        string $field,
        ?int $minimum = null,
        ?int $maximum = null,
        array $safeContext = []
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->integer(
            resource: $resource,
            value: $value,
            field: $field,
            minimum: $minimum,
            maximum: $maximum,
            safeContext: $safeContext
        );
    }

    /**
     * @param array<string, mixed> $safeContext
     */
    private function integer(
        ErpResource $resource,
        mixed $value,
        string $field,
        ?int $minimum = null,
        ?int $maximum = null,
        array $safeContext = []
    ): int {
        if (is_int($value)) {
            $integer = $value;
        } elseif (
            is_string($value)
            && preg_match(
                '/^\d+$/',
                trim($value)
            )
        ) {
            $integer = (int) trim($value);
        } else {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    "the [{$field}] pagination value is invalid",

                safeContext:
                    $safeContext
            );
        }

        if (
            $minimum !== null
            && $integer < $minimum
        ) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    "the [{$field}] pagination value is below its minimum",

                safeContext:
                    $safeContext
            );
        }

        if (
            $maximum !== null
            && $integer > $maximum
        ) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    "the [{$field}] pagination value exceeds its maximum",

                safeContext:
                    $safeContext
            );
        }

        return $integer;
    }

    /**
     * @param array<string, mixed> $safeContext
     */
    private function optionalDateTime(
        ErpResource $resource,
        mixed $value,
        string $field,
        array $safeContext = []
    ): ?CarbonImmutable {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            ! is_string($value)
            && ! is_int($value)
        ) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    "the [{$field}] timestamp is invalid",

                safeContext:
                    $safeContext
            );
        }

        try {
            return CarbonImmutable::parse(
                (string) $value
            );
        } catch (Throwable) {
            throw ErpInvalidResponseException::forResource(
                resource: $resource,

                reason:
                    "the [{$field}] timestamp is invalid",

                safeContext:
                    $safeContext
            );
        }
    }

    private function optionalString(
        mixed $value
    ): ?string {
        if (
            ! is_string($value)
            && ! is_int($value)
        ) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value === ''
            ? null
            : $value;
    }
}