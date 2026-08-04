<?php

namespace App\Exceptions\ERP;

use App\DTOs\ERP\ErpSourceRecord;
use RuntimeException;

final class ErpMappingException extends RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    private array $safeContext;

    /**
     * @param array<string, mixed> $safeContext
     */
    private function __construct(
        string $message,
        array $safeContext
    ) {
        parent::__construct($message);

        $this->safeContext =
            $safeContext;
    }

    /**
     * @param list<string> $aliases
     */
    public static function missingField(
        ErpSourceRecord $record,
        string $field,
        array $aliases = []
    ): self {
        return new self(
            message:
                "The ERP record is missing the required [{$field}] field.",

            safeContext:
                self::contextFor(
                    $record,
                    [
                        'field' => $field,
                        'aliases' => $aliases,
                    ]
                )
        );
    }

    public static function invalidField(
        ErpSourceRecord $record,
        string $field,
        string $expectedType
    ): self {
        return new self(
            message:
                "The ERP record contains an invalid [{$field}] field.",

            safeContext:
                self::contextFor(
                    $record,
                    [
                        'field' => $field,

                        'expected_type' =>
                            $expectedType,
                    ]
                )
        );
    }

    public static function unsupportedRecord(
        ErpSourceRecord $record
    ): self {
        return new self(
            message:
                'No ERP mapper supports the supplied source record.',

            safeContext:
                self::contextFor($record)
        );
    }

    public static function invalidChronology(
        ErpSourceRecord $record,
        string $startField,
        string $endField
    ): self {
        return new self(
            message:
                "The ERP fields [{$startField}] and [{$endField}] form an invalid time range.",

            safeContext:
                self::contextFor(
                    $record,
                    [
                        'start_field' =>
                            $startField,

                        'end_field' =>
                            $endField,
                    ]
                )
        );
    }

    /**
     * This context intentionally excludes the ERP payload.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->safeContext;
    }

    /**
     * @param array<string, mixed> $additional
     *
     * @return array<string, mixed>
     */
    private static function contextFor(
        ErpSourceRecord $record,
        array $additional = []
    ): array {
        return [
            'source_system' =>
                $record
                    ->identity
                    ->sourceSystem,

            'resource' =>
                $record
                    ->identity
                    ->resource
                    ->value,

            'external_id' =>
                $record
                    ->identity
                    ->externalId,

            'checksum' =>
                $record->checksum,

            ...$additional,
        ];
    }
}