<?php

namespace App\Services\ERP\Mapping;

use App\DTOs\ERP\ErpSourceRecord;
use App\Exceptions\ERP\ErpMappingException;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class ErpPayloadReader
{
    /**
     * @var array<string, mixed>
     */
    private array $payload;

    public function __construct(
        private readonly ErpSourceRecord $record
    ) {
        $this->payload =
            $record->attributes;
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredString(
        string $field,
        array $aliases = [],
        int $maximumLength = 255
    ): string {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (! $found) {
            throw ErpMappingException::missingField(
                $this->record,
                $field,
                $aliases
            );
        }

        if (
            ! is_string($value)
            && ! is_int($value)
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'non-empty string'
            );
        }

        $value = trim(
            (string) $value
        );

        if (
            $value === ''
            || mb_strlen($value) > $maximumLength
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                "non-empty string up to {$maximumLength} characters"
            );
        }

        return $value;
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalString(
        string $field,
        array $aliases = [],
        int $maximumLength = 255
    ): ?string {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (
            ! $found
            || $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            ! is_string($value)
            && ! is_int($value)
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'string or null'
            );
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value) > $maximumLength
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                "string up to {$maximumLength} characters"
            );
        }

        return $value;
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredReference(
        string $field,
        array $aliases = []
    ): string {
        return $this->requiredString(
            field: $field,
            aliases: $aliases,
            maximumLength: 120
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalReference(
        string $field,
        array $aliases = []
    ): ?string {
        return $this->optionalString(
            field: $field,
            aliases: $aliases,
            maximumLength: 120
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalBoolean(
        string $field,
        array $aliases = [],
        bool $default = false
    ): bool {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (
            ! $found
            || $value === null
            || $value === ''
        ) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (
            is_int($value)
            || is_float($value)
        ) {
            if ((int) $value === 1) {
                return true;
            }

            if ((int) $value === 0) {
                return false;
            }
        }

        if (is_string($value)) {
            return match (
                strtolower(trim($value))
            ) {
                '1',
                'true',
                'yes',
                'active',
                'enabled',
                'resolved' => true,

                '0',
                'false',
                'no',
                'inactive',
                'disabled',
                'open',
                'unresolved' => false,

                default =>
                    throw ErpMappingException::invalidField(
                        $this->record,
                        $field,
                        'boolean'
                    ),
            };
        }

        throw ErpMappingException::invalidField(
            $this->record,
            $field,
            'boolean'
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredInteger(
        string $field,
        array $aliases = [],
        ?int $minimum = null,
        ?int $maximum = null
    ): int {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (! $found) {
            throw ErpMappingException::missingField(
                $this->record,
                $field,
                $aliases
            );
        }

        return $this->parseInteger(
            $field,
            $value,
            $minimum,
            $maximum
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalInteger(
        string $field,
        array $aliases = [],
        ?int $default = null,
        ?int $minimum = null,
        ?int $maximum = null
    ): ?int {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (
            ! $found
            || $value === null
            || $value === ''
        ) {
            return $default;
        }

        return $this->parseInteger(
            $field,
            $value,
            $minimum,
            $maximum
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredDecimal(
        string $field,
        array $aliases = [],
        int $scale = 3,
        ?float $minimum = null,
        ?float $maximum = null
    ): string {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (! $found) {
            throw ErpMappingException::missingField(
                $this->record,
                $field,
                $aliases
            );
        }

        return $this->parseDecimal(
            $field,
            $value,
            $scale,
            $minimum,
            $maximum
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalDecimal(
        string $field,
        array $aliases = [],
        ?string $default = null,
        int $scale = 3,
        ?float $minimum = null,
        ?float $maximum = null
    ): ?string {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (
            ! $found
            || $value === null
            || $value === ''
        ) {
            return $default;
        }

        return $this->parseDecimal(
            $field,
            $value,
            $scale,
            $minimum,
            $maximum
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredDateTime(
        string $field,
        array $aliases = []
    ): CarbonImmutable {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (! $found) {
            throw ErpMappingException::missingField(
                $this->record,
                $field,
                $aliases
            );
        }

        return $this->parseDateTime(
            $field,
            $value
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalDateTime(
        string $field,
        array $aliases = []
    ): ?CarbonImmutable {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (
            ! $found
            || $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->parseDateTime(
            $field,
            $value
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredDate(
        string $field,
        array $aliases = []
    ): CarbonImmutable {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (! $found) {
            throw ErpMappingException::missingField(
                $this->record,
                $field,
                $aliases
            );
        }

        return $this->parseDate(
            $field,
            $value
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalDate(
        string $field,
        array $aliases = []
    ): ?CarbonImmutable {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (
            ! $found
            || $value === null
            || $value === ''
        ) {
            return null;
        }

        return $this->parseDate(
            $field,
            $value
        );
    }

    /**
     * @param list<string> $aliases
     */
    public function requiredTime(
        string $field,
        array $aliases = []
    ): string {
        [$found, $value] =
            $this->find(
                $field,
                $aliases
            );

        if (! $found) {
            throw ErpMappingException::missingField(
                $this->record,
                $field,
                $aliases
            );
        }

        if (
            ! is_string($value)
            && ! $value instanceof DateTimeInterface
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'time'
            );
        }

        try {
            return CarbonImmutable::parse(
                $value
            )->format('H:i:s');
        } catch (Throwable) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'valid time'
            );
        }
    }

    /**
     * @param list<string> $aliases
     */
    public function optionalEmail(
        string $field,
        array $aliases = []
    ): ?string {
        $email = $this->optionalString(
            field: $field,
            aliases: $aliases,
            maximumLength: 255
        );

        if ($email === null) {
            return null;
        }

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'valid email address'
            );
        }

        return strtolower($email);
    }

    /**
     * @param list<string> $aliases
     *
     * @return array{0: bool, 1: mixed}
     */

    /**
     * @param list<string> $aliases
     *
     * @return array{0: bool, 1: mixed}
     */
    private function find(
        string $field,
        array $aliases
    ): array {
        foreach (
            [
                $field,
                ...$aliases,
            ] as $candidate
        ) {
            /*
             * Preserve support for literal top-level keys, including
             * unusual ERP field names that themselves contain dots.
             */
            if (
                array_key_exists(
                    $candidate,
                    $this->payload
                )
            ) {
                return [
                    true,
                    $this->payload[$candidate],
                ];
            }

            [$found, $value] =
                $this->findNestedValue(
                    $candidate
                );

            if ($found) {
                return [
                    true,
                    $value,
                ];
            }
        }

        return [
            false,
            null,
        ];
    }

    /**
     * Resolve a safe dot-notated path such as product.external_id.
     *
     * @return array{0: bool, 1: mixed}
     */
    private function findNestedValue(
        string $path
    ): array {
        if (
            ! str_contains(
                $path,
                '.'
            )
        ) {
            return [
                false,
                null,
            ];
        }

        $current = $this->payload;

        foreach (
            explode('.', $path)
            as $segment
        ) {
            if (
                $segment === ''
                || ! is_array($current)
                || ! array_key_exists(
                    $segment,
                    $current
                )
            ) {
                return [
                    false,
                    null,
                ];
            }

            $current =
                $current[$segment];
        }

        return [
            true,
            $current,
        ];
    }

    private function parseInteger(
        string $field,
        mixed $value,
        ?int $minimum,
        ?int $maximum
    ): int {
        if (is_int($value)) {
            $integer = $value;
        } elseif (
            is_float($value)
            && floor($value) === $value
        ) {
            $integer = (int) $value;
        } elseif (
            is_string($value)
            && preg_match(
                '/^-?\d+$/',
                trim($value)
            )
        ) {
            $integer = (int) trim($value);
        } else {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'integer'
            );
        }

        if (
            $minimum !== null
            && $integer < $minimum
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                "integer greater than or equal to {$minimum}"
            );
        }

        if (
            $maximum !== null
            && $integer > $maximum
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                "integer less than or equal to {$maximum}"
            );
        }

        return $integer;
    }

    private function parseDecimal(
        string $field,
        mixed $value,
        int $scale,
        ?float $minimum,
        ?float $maximum
    ): string {
        if (
            ! is_string($value)
            && ! is_int($value)
            && ! is_float($value)
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'decimal number'
            );
        }

        if (
            is_string($value)
            && ! is_numeric(trim($value))
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'decimal number'
            );
        }

        $number = (float) $value;

        if (! is_finite($number)) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'finite decimal number'
            );
        }

        if (
            $minimum !== null
            && $number < $minimum
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                "decimal greater than or equal to {$minimum}"
            );
        }

        if (
            $maximum !== null
            && $number > $maximum
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                "decimal less than or equal to {$maximum}"
            );
        }

        return number_format(
            $number,
            $scale,
            '.',
            ''
        );
    }

    private function parseDateTime(
        string $field,
        mixed $value
    ): CarbonImmutable {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(
                $value
            );
        }

        if (
            ! is_string($value)
            && ! is_int($value)
        ) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'date and time'
            );
        }

        try {
            return CarbonImmutable::parse(
                (string) $value
            );
        } catch (Throwable) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'valid date and time'
            );
        }
    }

    private function parseDate(
        string $field,
        mixed $value
    ): CarbonImmutable {
        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance(
                    $value
                )->startOfDay();
            }

            if (
                ! is_string($value)
                && ! is_int($value)
            ) {
                throw new \InvalidArgumentException();
            }

            return CarbonImmutable::parse(
                (string) $value
            )->startOfDay();
        } catch (Throwable) {
            throw ErpMappingException::invalidField(
                $this->record,
                $field,
                'valid date'
            );
        }
    }
}
