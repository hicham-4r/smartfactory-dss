<?php

namespace App\DTOs\ERP;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ErpSyncCursor
{
    public ?string $opaqueToken;

    public function __construct(
        public ?CarbonImmutable $updatedSince = null,
        ?string $opaqueToken = null,
        public ?int $sourceVersion = null,
    ) {
        $opaqueToken = $opaqueToken === null
            ? null
            : trim($opaqueToken);

        if ($opaqueToken === '') {
            $opaqueToken = null;
        }

        if (
            $opaqueToken !== null
            && mb_strlen($opaqueToken) > 1000
        ) {
            throw new InvalidArgumentException(
                'ERP cursor token may not exceed 1000 characters.'
            );
        }

        if (
            $sourceVersion !== null
            && $sourceVersion < 0
        ) {
            throw new InvalidArgumentException(
                'ERP source version cannot be negative.'
            );
        }

        $this->opaqueToken = $opaqueToken;
    }

    public static function initial(): self
    {
        return new self();
    }

    public function withOpaqueToken(
        ?string $opaqueToken
    ): self {
        return new self(
            updatedSince: $this->updatedSince,
            opaqueToken: $opaqueToken,
            sourceVersion: $this->sourceVersion,
        );
    }

    public function withUpdatedSince(
        ?CarbonImmutable $updatedSince
    ): self {
        return new self(
            updatedSince: $updatedSince,
            opaqueToken: $this->opaqueToken,
            sourceVersion: $this->sourceVersion,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toQueryParameters(): array
    {
        $parameters = [];

        if ($this->updatedSince !== null) {
            $parameters['updated_since'] =
                $this->updatedSince
                    ->utc()
                    ->toIso8601String();
        }

        if ($this->opaqueToken !== null) {
            $parameters['cursor'] =
                $this->opaqueToken;
        }

        if ($this->sourceVersion !== null) {
            $parameters['source_version'] =
                $this->sourceVersion;
        }

        return $parameters;
    }
}