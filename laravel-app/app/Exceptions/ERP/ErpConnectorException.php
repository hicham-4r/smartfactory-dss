<?php

namespace App\Exceptions\ERP;

use App\Enums\ERP\ErpResource;
use RuntimeException;
use Throwable;

class ErpConnectorException extends RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    private array $safeContext;

    /**
     * @param array<string, mixed> $safeContext
     */
    public function __construct(
        string $message,
        private readonly bool $retryable = false,
        private readonly ?ErpResource $resource = null,
        array $safeContext = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            0,
            $previous
        );

        $this->safeContext =
            $this->sanitizeContext(
                $safeContext
            );
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function resource(): ?ErpResource
    {
        return $this->resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'resource' =>
                $this->resource?->value,

            'retryable' =>
                $this->retryable,

            ...$this->safeContext,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function sanitizeContext(
        array $context
    ): array {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $key = (string) $key;

            if (
                preg_match(
                    '/token|password|secret|authorization|credential|api[_-]?key/i',
                    $key
                )
            ) {
                $sanitized[$key] =
                    '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] =
                    $this->sanitizeContext(
                        $value
                    );

                continue;
            }

            if (
                is_string($value)
                || is_int($value)
                || is_float($value)
                || is_bool($value)
                || $value === null
            ) {
                $sanitized[$key] = $value;

                continue;
            }

            $sanitized[$key] =
                get_debug_type($value);
        }

        return $sanitized;
    }
}