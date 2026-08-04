<?php

namespace App\DTOs\AI\Analytics;

use App\DTOs\Analytics\MaintenanceKpiSummary;
use App\DTOs\Analytics\ProductionBreakdownReport;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\QualityKpiSummary;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonSerializable;

final readonly class AnalyticsSnapshotContract implements
    JsonSerializable
{
    public const CONTRACT_NAME =
        'smartfactory.analytics.snapshot';

    public const CONTRACT_VERSION = 'v1';

    public const SOURCE_APPLICATION =
        'smartfactory-dss-laravel';

    public const DATA_CLASSIFICATION =
        'simulated_prototype';

    public string $snapshotId;

    public string $sourceSystem;

    /**
     * @param string $snapshotId UUID generated once and reused as
     *        the idempotency key for safe retries.
     */
    public function __construct(
        string $snapshotId,
        public CarbonImmutable $generatedAt,
        public string $timezone,
        string $sourceSystem =
            'simulated_sage',
        public ?ProductionKpiSummary $productionKpis = null,
        public ?ProductionBreakdownReport $productionBreakdowns = null,
        public ?MaintenanceKpiSummary $maintenanceKpis = null,
        public ?QualityKpiSummary $qualityKpis = null,
    ) {
        $snapshotId = strtolower(
            trim($snapshotId)
        );

        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $snapshotId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'The analytics snapshot identifier must be a valid UUID.'
            );
        }

        if (
            ! in_array(
                $timezone,
                DateTimeZone::listIdentifiers(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'The analytics snapshot timezone is invalid.'
            );
        }

        $sourceSystem = strtolower(
            trim($sourceSystem)
        );

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{0,49}$/',
                $sourceSystem
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'The analytics source system is invalid.'
            );
        }

        if ($this->sectionNames() === []) {
            throw new InvalidArgumentException(
                'At least one verified analytics section is required.'
            );
        }

        foreach (
            $this->sectionTimezones()
            as $sectionTimezone
        ) {
            if ($sectionTimezone !== $timezone) {
                throw new InvalidArgumentException(
                    'Every analytics section must use the snapshot timezone.'
                );
            }
        }

        $this->snapshotId = $snapshotId;
        $this->sourceSystem = $sourceSystem;
    }

    /**
     * @return list<string>
     */
    public function sectionNames(): array
    {
        $sections = [];

        if ($this->productionKpis !== null) {
            $sections[] = 'production_kpis';
        }

        if (
            $this->productionBreakdowns
            !== null
        ) {
            $sections[] =
                'production_breakdowns';
        }

        if ($this->maintenanceKpis !== null) {
            $sections[] =
                'maintenance_kpis';
        }

        if ($this->qualityKpis !== null) {
            $sections[] = 'quality_kpis';
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];

        if ($this->productionKpis !== null) {
            $payload['production_kpis'] =
                $this->productionKpis
                    ->toArray();
        }

        if (
            $this->productionBreakdowns
            !== null
        ) {
            $payload[
                'production_breakdowns'
            ] = $this
                ->productionBreakdowns
                ->toArray();
        }

        if ($this->maintenanceKpis !== null) {
            $payload['maintenance_kpis'] =
                $this->maintenanceKpis
                    ->toArray();
        }

        if ($this->qualityKpis !== null) {
            $payload['quality_kpis'] =
                $this->qualityKpis
                    ->toArray();
        }

        return [
            'metadata' => [
                'snapshot_id' =>
                    $this->snapshotId,

                'contract_name' =>
                    self::CONTRACT_NAME,

                'contract_version' =>
                    self::CONTRACT_VERSION,

                'source_application' =>
                    self::SOURCE_APPLICATION,

                'source_system' =>
                    $this->sourceSystem,

                'data_classification' =>
                    self::DATA_CLASSIFICATION,

                'generated_at' =>
                    $this->generatedAt
                        ->utc()
                        ->toIso8601String(),

                'timezone' =>
                    $this->timezone,
            ],

            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<string>
     */
    private function sectionTimezones(): array
    {
        $timezones = [];

        if ($this->productionKpis !== null) {
            $timezones[] =
                $this->productionKpis
                    ->filter
                    ->timezone;
        }

        if (
            $this->productionBreakdowns
            !== null
        ) {
            $timezones[] =
                $this->productionBreakdowns
                    ->filter
                    ->timezone;
        }

        if ($this->maintenanceKpis !== null) {
            $timezones[] =
                $this->maintenanceKpis
                    ->filter
                    ->timezone;
        }

        if ($this->qualityKpis !== null) {
            $timezones[] =
                $this->qualityKpis
                    ->filter
                    ->timezone;
        }

        return $timezones;
    }
}
