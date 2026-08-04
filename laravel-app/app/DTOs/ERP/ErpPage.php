<?php

namespace App\DTOs\ERP;

use App\Enums\ERP\ErpResource;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ErpPage
{
    /**
     * @var list<ErpSourceRecord>
     */
    public array $records;

    public ?string $nextCursor;

    /**
     * @param list<ErpSourceRecord> $records
     */
    public function __construct(
        public ErpResource $resource,
        array $records,
        public int $currentPage,
        public int $perPage,
        public ?int $total = null,
        public ?int $nextPage = null,
        ?string $nextCursor = null,
        public ?CarbonImmutable $fetchedAt = null,
        public ?string $responseId = null,
    ) {
        if ($currentPage < 1) {
            throw new InvalidArgumentException(
                'ERP response page must be at least 1.'
            );
        }

        if ($perPage < 1 || $perPage > 200) {
            throw new InvalidArgumentException(
                'ERP response page size must be between 1 and 200.'
            );
        }

        if ($total !== null && $total < 0) {
            throw new InvalidArgumentException(
                'ERP response total cannot be negative.'
            );
        }

        if (
            $nextPage !== null
            && $nextPage <= $currentPage
        ) {
            throw new InvalidArgumentException(
                'ERP next page must be greater than the current page.'
            );
        }

        $nextCursor = $nextCursor === null
            ? null
            : trim($nextCursor);

        if ($nextCursor === '') {
            $nextCursor = null;
        }

        if (
            $nextCursor !== null
            && mb_strlen($nextCursor) > 1000
        ) {
            throw new InvalidArgumentException(
                'ERP next cursor may not exceed 1000 characters.'
            );
        }

        foreach ($records as $record) {
            if (
                ! $record
                instanceof ErpSourceRecord
            ) {
                throw new InvalidArgumentException(
                    'ERP pages may contain only ErpSourceRecord objects.'
                );
            }

            if (
                $record->identity->resource
                !== $resource
            ) {
                throw new InvalidArgumentException(
                    'ERP page contains a record from another resource.'
                );
            }
        }

        $this->records =
            array_values($records);

        $this->nextCursor = $nextCursor;
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return $this->records === [];
    }

    public function hasMore(): bool
    {
        return $this->nextPage !== null
            || $this->nextCursor !== null;
    }

    public function nextRequest(
        ErpPageRequest $currentRequest
    ): ?ErpPageRequest {
        if (! $this->hasMore()) {
            return null;
        }

        $cursor = $currentRequest->cursor;

        if ($this->nextCursor !== null) {
            $cursor = (
                $cursor
                ?? ErpSyncCursor::initial()
            )->withOpaqueToken(
                $this->nextCursor
            );
        }

        return new ErpPageRequest(
            page:
                $this->nextPage
                ?? ($currentRequest->page + 1),

            perPage:
                $currentRequest->perPage,

            cursor: $cursor,

            filters:
                $currentRequest->filters,
        );
    }
}