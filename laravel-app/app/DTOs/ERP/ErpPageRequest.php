<?php

namespace App\DTOs\ERP;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use UnitEnum;

final readonly class ErpPageRequest
{
    /**
     * @var array<string, mixed>
     */
    public array $filters;

    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public int $page = 1,
        public int $perPage = 100,
        public ?ErpSyncCursor $cursor = null,
        array $filters = [],
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException(
                'ERP page number must be at least 1.'
            );
        }

        if ($perPage < 1 || $perPage > 200) {
            throw new InvalidArgumentException(
                'ERP page size must be between 1 and 200.'
            );
        }

        $this->filters = $this->normalizeFilters(
            $filters
        );
    }

    public function nextPage(
        ?string $nextCursor = null
    ): self {
        $cursor = $this->cursor;

        if ($nextCursor !== null) {
            $cursor = (
                $cursor
                ?? ErpSyncCursor::initial()
            )->withOpaqueToken($nextCursor);
        }

        return new self(
            page: $this->page + 1,
            perPage: $this->perPage,
            cursor: $cursor,
            filters: $this->filters,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQueryParameters(): array
    {
        return [
            ...$this->filters,
            'page' => $this->page,
            'per_page' => $this->perPage,
            ...(
                $this->cursor
                    ?->toQueryParameters()
                ?? []
            ),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function normalizeFilters(
        array $filters
    ): array {
        $reserved = [
            'page',
            'per_page',
            'updated_since',
            'cursor',
            'source_version',
        ];

        $normalized = [];

        foreach ($filters as $key => $value) {
            if (
                ! is_string($key)
                || ! preg_match(
                    '/^[a-z][a-z0-9_]*$/',
                    $key
                )
            ) {
                throw new InvalidArgumentException(
                    'ERP filter keys must use snake_case identifiers.'
                );
            }

            if (
                in_array(
                    $key,
                    $reserved,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    "ERP filter [{$key}] is reserved."
                );
            }

            $normalized[$key] =
                $this->normalizeFilterValue(
                    $value
                );
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private function normalizeFilterValue(
        mixed $value
    ): mixed {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(
                $value
            )
                ->utc()
                ->toIso8601String();
        }

        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] =
                    $this->normalizeFilterValue(
                        $item
                    );
            }

            return $normalized;
        }

        throw new InvalidArgumentException(
            'ERP filters may contain only scalar, date, enum, array, or null values.'
        );
    }
}