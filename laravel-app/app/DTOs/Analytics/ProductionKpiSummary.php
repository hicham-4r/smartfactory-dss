<?php

namespace App\DTOs\Analytics;

use App\Enums\Production\ProductionOrderStatus;
use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class ProductionKpiSummary implements JsonSerializable
{
    /**
     * @param list<ProductionKpiUnitSummary> $units
     */
    public function __construct(
        public AnalyticsFilter $filter,
        public CarbonImmutable $generatedAt,
        public array $units,
        public int $recordCount,
        public int $validatedRecordCount,
        public int $provisionalRecordCount,
        public int $targetOrderCount,
        public int $runtimeMinutes,
        public int $downtimeMinutes,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->units === [];
    }

    public function hasMixedUnits(): bool
    {
        return count($this->units) > 1;
    }

    public function primaryUnit(): ?ProductionKpiUnitSummary
    {
        return count($this->units) === 1
            ? $this->units[0]
            : null;
    }

    public function isProvisional(): bool
    {
        return $this->provisionalRecordCount > 0;
    }

    /**
     * Retained for backward compatibility with the existing Blade template
     * and tests. The execution-only dashboard never exposes planning views.
     */
    public function isPlanningView(): bool
    {
        return false;
    }

    public function dataBasisLabel(): string
    {
        $shiftBasis = $this->filter->shiftId !== null
            ? ' The shift denominator uses planned batch quantities for batches that contain records from the selected shift.'
            : '';

        $label = match ($this->filter->status) {
            ProductionOrderStatus::InProgress->value =>
                $this->isProvisional()
                    ? 'Live in-progress view using validated and pending production records. Pending values are provisional.'
                    : 'In-progress view using validated production records. No pending record is currently included.',

            ProductionOrderStatus::Completed->value =>
                'Final completed-order view using validated production records only.',

            default =>
                $this->isProvisional()
                    ? 'Execution overview using completed validated records and in-progress validated or pending records. Pending values are provisional.'
                    : 'Execution overview using validated records from in-progress and completed production.',
        };

        return $label.$shiftBasis;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filter' => $this->filter->toArray(),
            'generated_at' => $this->generatedAt
                ->utc()
                ->toIso8601String(),
            'data_basis' => $this->dataBasisLabel(),
            'unit_count' => count($this->units),
            'has_mixed_units' => $this->hasMixedUnits(),
            'is_provisional' => $this->isProvisional(),
            'record_count' => $this->recordCount,
            'validated_record_count' => $this->validatedRecordCount,
            'provisional_record_count' => $this->provisionalRecordCount,
            'target_order_count' => $this->targetOrderCount,
            'runtime_minutes' => $this->runtimeMinutes,
            'downtime_minutes' => $this->downtimeMinutes,
            'units' => array_map(
                static fn (
                    ProductionKpiUnitSummary $unit
                ): array => $unit->toArray(),
                $this->units
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
