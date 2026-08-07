<?php

namespace App\Models\Concerns;

use App\Exceptions\Production\OptimisticLockException;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use UnitEnum;

trait HasOptimisticLocking
{
    /**
     * Atomically update the record only when lock_version still matches.
     *
     * This method is intended to be called through a controlled
     * repository or domain service.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateWithOptimisticLock(
        array $attributes,
        int $expectedVersion
    ): static {
        if (! $this->exists) {
            throw new LogicException(
                'Optimistic updates require an existing model.'
            );
        }

        if ($expectedVersion < 1) {
            throw new LogicException(
                'The expected lock version must be at least 1.'
            );
        }

        unset(
            $attributes[$this->getKeyName()],
            $attributes['created_at'],
            $attributes['lock_version']
        );

        $attributes = $this->normalizeOptimisticAttributes(
            $attributes
        );

        $attributes['lock_version'] =
            $expectedVersion + 1;

        if ($this->usesTimestamps()) {
            $attributes[$this->getUpdatedAtColumn()] =
                $this->freshTimestampString();
        }

        $affected = static::query()
            ->whereKey($this->getKey())
            ->where(
                'lock_version',
                $expectedVersion
            )
            ->update($attributes);

        if ($affected !== 1) {
            throw OptimisticLockException::stale(
                $this,
                $expectedVersion
            );
        }

        /*
         * Query-builder updates preserve the atomic lock-version check,
         * but Laravel does not dispatch Eloquent model events for them.
         * Rehydrate this model while retaining its previous originals,
         * calculate the real changes, and dispatch the successful
         * post-update event exactly once.
         */
        $fresh = static::query()
            ->findOrFail(
                $this->getKey()
            );

        $this->setRawAttributes(
            $fresh->getAttributes(),
            false
        );

        $this->syncChanges();

        $this->fireModelEvent(
            'updated',
            false
        );

        return $this->refresh();
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function normalizeOptimisticAttributes(
        array $attributes
    ): array {
        foreach ($attributes as $key => $value) {
            if ($value instanceof BackedEnum) {
                $attributes[$key] = $value->value;

                continue;
            }

            if ($value instanceof UnitEnum) {
                $attributes[$key] = $value->name;

                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $attributes[$key] =
                    $this->fromDateTime($value);
            }
        }

        return $attributes;
    }
}