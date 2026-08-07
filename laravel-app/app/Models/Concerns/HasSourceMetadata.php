<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

trait HasSourceMetadata
{
    /**
     * Restrict a query to active records.
     */
    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            $this->qualifyColumn('is_active'),
            true
        );
    }

    /**
     * Restrict a query to records from one source system.
     */
    public function scopeFromSource(
        Builder $query,
        string $sourceSystem
    ): Builder {
        return $query->where(
            $this->qualifyColumn('source_system'),
            mb_strtolower(trim($sourceSystem))
        );
    }

    /**
     * Restrict a query to records linked to an external source.
     */
    public function scopeExternallyManaged(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull(
                $this->qualifyColumn('external_id')
            )
            ->where(
                $this->qualifyColumn('external_id'),
                '!=',
                ''
            );
    }

    /**
     * Restrict a query to records synchronized after a date.
     */
    public function scopeSyncedAfter(
        Builder $query,
        DateTimeInterface $date
    ): Builder {
        return $query->where(
            $this->qualifyColumn('last_synced_at'),
            '>=',
            $date
        );
    }

    /**
     * Determine whether the record belongs to an external source.
     */
    public function isExternallyManaged(): bool
    {
        return is_string($this->external_id)
            && trim($this->external_id) !== '';
    }

    /**
     * Return a readable source identity.
     */
    public function sourceIdentity(): ?string
    {
        if (! $this->isExternallyManaged()) {
            return null;
        }

        return sprintf(
            '%s:%s',
            $this->source_system,
            $this->external_id
        );
    }

    /**
     * Common source-traceability casts.
     *
     * @return array<string, string>
     */
    protected function sourceMetadataCasts(): array
    {
        return [
            'source_version' => 'integer',
            'source_updated_at' => 'immutable_datetime',
            'last_synced_at' => 'immutable_datetime',
        ];
    }
}