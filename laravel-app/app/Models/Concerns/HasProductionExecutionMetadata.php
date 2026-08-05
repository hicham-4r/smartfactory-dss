<?php

namespace App\Models\Concerns;

use App\Enums\Production\ProductionImportStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasProductionExecutionMetadata
{
    use HasSourceMetadata;

    /**
     * Restrict a query to records with one import status.
     */
    public function scopeImportStatus(
        Builder $query,
        ProductionImportStatus|string $status
    ): Builder {
        $value = $status instanceof ProductionImportStatus
            ? $status->value
            : ProductionImportStatus::from($status)->value;

        return $query->where(
            $this->qualifyColumn('import_status'),
            $value
        );
    }

    public function hasImportFailure(): bool
    {
        return $this->import_status
            === ProductionImportStatus::Failed;
    }

    /**
     * @return array<string, string>
     */
    protected function productionExecutionMetadataCasts(): array
    {
        return [
            ...$this->sourceMetadataCasts(),

            'import_status' =>
                ProductionImportStatus::class,

            'lock_version' => 'integer',
        ];
    }
}