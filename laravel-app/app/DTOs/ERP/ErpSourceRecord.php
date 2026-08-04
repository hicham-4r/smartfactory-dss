<?php

namespace App\DTOs\ERP;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use UnitEnum;

final readonly class ErpSourceRecord
{
    /**
     * @var array<string, mixed>
     */
    public array $attributes;

    public string $checksum;

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws JsonException
     */
    public function __construct(
        public ErpSourceIdentity $identity,
        array $attributes,
        public ?int $sourceVersion = null,
        public ?CarbonImmutable $sourceUpdatedAt = null,
        public ?CarbonImmutable $receivedAt = null,
    ) {
        if (
            $sourceVersion !== null
            && $sourceVersion < 0
        ) {
            throw new InvalidArgumentException(
                'ERP record source version cannot be negative.'
            );
        }

        $this->attributes =
            $this->canonicalizeArray(
                $attributes
            );

        $this->checksum = hash(
            'sha256',
            json_encode(
                $this->attributes,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
        );
    }

    public function isNewerThan(
        ?int $knownSourceVersion
    ): bool {
        if ($this->sourceVersion === null) {
            return $knownSourceVersion === null;
        }

        return $knownSourceVersion === null
            || $this->sourceVersion
                > $knownSourceVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->identity->toArray(),

            'source_version' =>
                $this->sourceVersion,

            'source_updated_at' =>
                $this->sourceUpdatedAt
                    ?->utc()
                    ->toIso8601String(),

            'received_at' =>
                (
                    $this->receivedAt
                    ?? CarbonImmutable::now()
                )
                    ->utc()
                    ->toIso8601String(),

            'checksum' => $this->checksum,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function canonicalizeArray(
        array $value
    ): array {
        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new InvalidArgumentException(
                    'ERP record attribute keys must be strings.'
                );
            }

            $normalized[$key] =
                $this->canonicalizeValue(
                    $item
                );
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private function canonicalizeValue(
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
            if (array_is_list($value)) {
                return array_map(
                    fn (mixed $item): mixed =>
                        $this->canonicalizeValue(
                            $item
                        ),
                    $value
                );
            }

            return $this->canonicalizeArray(
                $value
            );
        }

        throw new InvalidArgumentException(
            'ERP record attributes must be JSON-compatible values.'
        );
    }
}