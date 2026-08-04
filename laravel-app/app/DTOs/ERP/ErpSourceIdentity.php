<?php

namespace App\DTOs\ERP;

use App\Enums\ERP\ErpResource;
use InvalidArgumentException;

final readonly class ErpSourceIdentity
{
    public string $sourceSystem;

    public string $externalId;

    public function __construct(
        string $sourceSystem,
        public ErpResource $resource,
        string $externalId
    ) {
        $sourceSystem = trim($sourceSystem);
        $externalId = trim($externalId);

        if ($sourceSystem === '') {
            throw new InvalidArgumentException(
                'ERP source system cannot be empty.'
            );
        }

        if (mb_strlen($sourceSystem) > 50) {
            throw new InvalidArgumentException(
                'ERP source system may not exceed 50 characters.'
            );
        }

        if (
            ! preg_match(
                '/^[a-z0-9][a-z0-9._-]*$/',
                $sourceSystem
            )
        ) {
            throw new InvalidArgumentException(
                'ERP source system contains unsupported characters.'
            );
        }

        if ($externalId === '') {
            throw new InvalidArgumentException(
                'ERP external identifier cannot be empty.'
            );
        }

        if (mb_strlen($externalId) > 120) {
            throw new InvalidArgumentException(
                'ERP external identifier may not exceed 120 characters.'
            );
        }

        $this->sourceSystem = $sourceSystem;
        $this->externalId = $externalId;
    }

    public function key(): string
    {
        return implode(
            '|',
            [
                $this->sourceSystem,
                $this->resource->value,
                $this->externalId,
            ]
        );
    }

    /**
     * @return array{
     *     source_system: string,
     *     resource: string,
     *     external_id: string
     * }
     */
    public function toArray(): array
    {
        return [
            'source_system' => $this->sourceSystem,
            'resource' => $this->resource->value,
            'external_id' => $this->externalId,
        ];
    }
}