<?php

namespace App\DTOs\AI\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AiReportExplanation
{
    public const CONTRACT_NAME = 'smartfactory.llm.explanation';

    public const CONTRACT_VERSION = 'v1';

    public const DATA_CLASSIFICATION = 'simulated_prototype';

    /**
     * @param  array{
     *     summary:string,
     *     observations:list<string>,
     *     suggested_human_checks:list<string>,
     *     limitations:list<string>,
     *     referenced_fact_keys:list<string>
     * }  $narrative
     */
    public function __construct(
        public string $explanationId,
        public string $explanationType,
        public string $role,
        public string $language,
        public string $requestId,
        public string $inferenceRequestId,
        public CarbonImmutable $attachedAt,
        public array $narrative,
    ) {
        $this->assertUuid($this->explanationId, 'explanation_id');
        $this->assertRequestId($this->requestId, 'request_id');
        $this->assertRequestId(
            $this->inferenceRequestId,
            'inference_request_id',
        );
        $this->assertRoleAllowed(
            explanationType: $this->explanationType,
            role: $this->role,
        );

        if (! in_array($this->language, ['en', 'fr'], true)) {
            throw new InvalidArgumentException(
                'The report explanation language is unsupported.',
            );
        }

        $this->validateNarrative($this->narrative);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGeneratedResult(
        array $payload,
        string $operation,
        string $inferenceRequestId,
        ?CarbonImmutable $attachedAt = null,
    ): self {
        self::assertExactKeys(
            $payload,
            [
                'status',
                'contract_name',
                'contract_version',
                'explanation_id',
                'explanation_type',
                'role',
                'language',
                'data_classification',
                'narrative',
                'request_id',
            ],
            'generated explanation',
        );

        if (
            ($payload['status'] ?? null) !== 'generated'
            || ($payload['contract_name'] ?? null) !== self::CONTRACT_NAME
            || ($payload['contract_version'] ?? null) !== self::CONTRACT_VERSION
            || ($payload['data_classification'] ?? null)
                !== self::DATA_CLASSIFICATION
        ) {
            throw new InvalidArgumentException(
                'The generated explanation contract is invalid.',
            );
        }

        $expectedType = self::typeForOperation($operation);
        if (($payload['explanation_type'] ?? null) !== $expectedType) {
            throw new InvalidArgumentException(
                'The generated explanation does not match the report operation.',
            );
        }

        $narrative = $payload['narrative'] ?? null;
        if (! is_array($narrative)) {
            throw new InvalidArgumentException(
                'The generated explanation narrative is missing.',
            );
        }

        return new self(
            explanationId: self::stringValue(
                $payload,
                'explanation_id',
                100,
            ),
            explanationType: $expectedType,
            role: self::stringValue($payload, 'role', 40),
            language: self::stringValue($payload, 'language', 10),
            requestId: self::stringValue($payload, 'request_id', 200),
            inferenceRequestId: $inferenceRequestId,
            attachedAt: $attachedAt ?? CarbonImmutable::now()->utc(),
            narrative: self::normalizedNarrative($narrative),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(
        array $payload,
        string $operation,
        string $reportRequestId,
    ): self {
        self::assertExactKeys(
            $payload,
            [
                'contract_name',
                'contract_version',
                'explanation_id',
                'explanation_type',
                'role',
                'language',
                'data_classification',
                'narrative',
                'request_id',
                'inference_request_id',
                'attached_at',
            ],
            'stored report explanation',
        );

        if (
            ($payload['contract_name'] ?? null) !== self::CONTRACT_NAME
            || ($payload['contract_version'] ?? null) !== self::CONTRACT_VERSION
            || ($payload['data_classification'] ?? null)
                !== self::DATA_CLASSIFICATION
        ) {
            throw new InvalidArgumentException(
                'The stored report explanation contract is invalid.',
            );
        }

        $expectedType = self::typeForOperation($operation);
        if (($payload['explanation_type'] ?? null) !== $expectedType) {
            throw new InvalidArgumentException(
                'The stored explanation does not match the report operation.',
            );
        }

        $inferenceRequestId = self::stringValue(
            $payload,
            'inference_request_id',
            200,
        );
        if (! hash_equals($reportRequestId, $inferenceRequestId)) {
            throw new InvalidArgumentException(
                'The stored explanation is not linked to the exact report inference.',
            );
        }

        $narrative = $payload['narrative'] ?? null;
        if (! is_array($narrative)) {
            throw new InvalidArgumentException(
                'The stored report explanation narrative is missing.',
            );
        }

        $attachedAt = self::stringValue($payload, 'attached_at', 80);

        try {
            $parsedAttachedAt = CarbonImmutable::parse($attachedAt);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                'The stored report explanation timestamp is invalid.',
                previous: $exception,
            );
        }

        return new self(
            explanationId: self::stringValue(
                $payload,
                'explanation_id',
                100,
            ),
            explanationType: $expectedType,
            role: self::stringValue($payload, 'role', 40),
            language: self::stringValue($payload, 'language', 10),
            requestId: self::stringValue($payload, 'request_id', 200),
            inferenceRequestId: $inferenceRequestId,
            attachedAt: $parsedAttachedAt,
            narrative: self::normalizedNarrative($narrative),
        );
    }

    public function matchesOperation(string $operation): bool
    {
        return $this->explanationType === self::typeForOperation($operation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contract_name' => self::CONTRACT_NAME,
            'contract_version' => self::CONTRACT_VERSION,
            'explanation_id' => $this->explanationId,
            'explanation_type' => $this->explanationType,
            'role' => $this->role,
            'language' => $this->language,
            'data_classification' => self::DATA_CLASSIFICATION,
            'narrative' => $this->narrative,
            'request_id' => $this->requestId,
            'inference_request_id' => $this->inferenceRequestId,
            'attached_at' => $this->attachedAt->utc()->toIso8601String(),
        ];
    }

    private static function typeForOperation(string $operation): string
    {
        return match ($operation) {
            'production_forecast' => 'production_forecast',
            'production_anomaly' => 'production_anomaly',
            'maintenance_risk' => 'maintenance_risk',
            default => throw new InvalidArgumentException(
                'The report operation cannot receive an AI explanation.',
            ),
        };
    }

    private function assertRoleAllowed(
        string $explanationType,
        string $role,
    ): void {
        $allowed = match ($explanationType) {
            'production_forecast',
            'production_anomaly' => [
                'production_supervisor',
                'production_manager',
                'administrator',
            ],
            'maintenance_risk' => [
                'maintenance_manager',
                'administrator',
            ],
            default => [],
        };

        if (! in_array($role, $allowed, true)) {
            throw new InvalidArgumentException(
                'The explanation role is not authorized for the report operation.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $narrative
     * @return array{
     *     summary:string,
     *     observations:list<string>,
     *     suggested_human_checks:list<string>,
     *     limitations:list<string>,
     *     referenced_fact_keys:list<string>
     * }
     */
    private static function normalizedNarrative(array $narrative): array
    {
        self::assertExactKeys(
            $narrative,
            [
                'summary',
                'observations',
                'suggested_human_checks',
                'limitations',
                'referenced_fact_keys',
            ],
            'explanation narrative',
        );

        return [
            'summary' => self::stringValue($narrative, 'summary', 600),
            'observations' => self::stringList(
                $narrative,
                'observations',
                1,
                5,
                300,
            ),
            'suggested_human_checks' => self::stringList(
                $narrative,
                'suggested_human_checks',
                1,
                5,
                300,
            ),
            'limitations' => self::stringList(
                $narrative,
                'limitations',
                1,
                12,
                400,
            ),
            'referenced_fact_keys' => self::factKeyList(
                $narrative,
                'referenced_fact_keys',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $narrative
     */
    private function validateNarrative(array $narrative): void
    {
        self::normalizedNarrative($narrative);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $expected
     */
    private static function assertExactKeys(
        array $payload,
        array $expected,
        string $label,
    ): void {
        $actual = array_keys($payload);
        sort($actual);
        sort($expected);

        if ($actual !== $expected) {
            throw new InvalidArgumentException(
                "The {$label} contains unsupported or missing fields.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function stringValue(
        array $payload,
        string $key,
        int $maximumLength,
    ): string {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "The explanation field [{$key}] must be text.",
            );
        }

        $normalized = trim($value);
        if (
            $normalized === ''
            || mb_strlen($normalized) > $maximumLength
            || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1
        ) {
            throw new InvalidArgumentException(
                "The explanation field [{$key}] is invalid.",
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function stringList(
        array $payload,
        string $key,
        int $minimum,
        int $maximum,
        int $maximumLength,
    ): array {
        $value = $payload[$key] ?? null;

        if (
            ! is_array($value)
            || count($value) < $minimum
            || count($value) > $maximum
        ) {
            throw new InvalidArgumentException(
                "The explanation list [{$key}] has an invalid size.",
            );
        }

        $normalized = [];
        $seen = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException(
                    "The explanation list [{$key}] contains a non-text item.",
                );
            }

            $text = trim($item);
            if (
                $text === ''
                || mb_strlen($text) > $maximumLength
                || preg_match('/[\x00-\x1F\x7F]/', $text) === 1
            ) {
                throw new InvalidArgumentException(
                    "The explanation list [{$key}] contains invalid text.",
                );
            }

            $folded = mb_strtolower($text);
            if (isset($seen[$folded])) {
                throw new InvalidArgumentException(
                    "The explanation list [{$key}] contains duplicates.",
                );
            }

            $seen[$folded] = true;
            $normalized[] = $text;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function factKeyList(
        array $payload,
        string $key,
    ): array {
        $items = self::stringList(
            $payload,
            $key,
            1,
            40,
            160,
        );

        foreach ($items as $item) {
            if (
                preg_match(
                    '/^facts\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/',
                    $item,
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'The explanation contains an invalid fact reference.',
                );
            }
        }

        return $items;
    }

    private function assertUuid(string $value, string $field): void
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException(
                "The explanation field [{$field}] must be a UUID.",
            );
        }
    }

    private function assertRequestId(string $value, string $field): void
    {
        if (
            $value === ''
            || strlen($value) > 200
            || preg_match('/^[A-Za-z0-9._:-]+$/', $value) !== 1
        ) {
            throw new InvalidArgumentException(
                "The explanation field [{$field}] is invalid.",
            );
        }
    }
}
