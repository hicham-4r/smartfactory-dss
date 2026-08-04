<?php

namespace Tests\Unit\AI;

use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;
use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\ProductionKpiUnitSummary;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AnalyticsSnapshotContractTest extends TestCase
{
    public function test_verified_production_dto_is_wrapped_without_recalculating_values(): void
    {
        $summary = $this->productionSummary(
            timezone: 'Africa/Casablanca'
        );

        $contract = new AnalyticsSnapshotContract(
            snapshotId:
                '11111111-1111-4111-8111-111111111111',
            generatedAt:
                CarbonImmutable::parse(
                    '2026-08-02T21:45:00Z'
                ),
            timezone:
                'Africa/Casablanca',
            productionKpis: $summary
        );

        $payload = $contract->toArray();

        self::assertSame(
            AnalyticsSnapshotContract
                ::CONTRACT_NAME,
            $payload['metadata'][
                'contract_name'
            ]
        );

        self::assertSame(
            'simulated_prototype',
            $payload['metadata'][
                'data_classification'
            ]
        );

        self::assertSame(
            $summary->toArray(),
            $payload['payload'][
                'production_kpis'
            ]
        );

        self::assertSame(
            [
                'production_kpis',
            ],
            $contract->sectionNames()
        );
    }

    public function test_contract_requires_at_least_one_verified_section(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new AnalyticsSnapshotContract(
            snapshotId:
                '11111111-1111-4111-8111-111111111111',
            generatedAt:
                CarbonImmutable::now(),
            timezone:
                'Africa/Casablanca'
        );
    }

    public function test_contract_rejects_section_timezone_mismatch(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        new AnalyticsSnapshotContract(
            snapshotId:
                '11111111-1111-4111-8111-111111111111',
            generatedAt:
                CarbonImmutable::now(),
            timezone:
                'UTC',
            productionKpis:
                $this->productionSummary(
                    timezone:
                        'Africa/Casablanca'
                )
        );
    }

    private function productionSummary(
        string $timezone
    ): ProductionKpiSummary {
        $filter = new AnalyticsFilter(
            startDate:
                CarbonImmutable::parse(
                    '2026-08-01',
                    $timezone
                ),
            endDate:
                CarbonImmutable::parse(
                    '2026-08-02',
                    $timezone
                ),
            timezone: $timezone
        );

        $unit =
            new ProductionKpiUnitSummary(
                quantityUnit: 'L',
                targetOrderCount: 1,
                recordCount: 1,
                validatedRecordCount: 1,
                provisionalRecordCount: 0,
                targetQuantity: '1000.000',
                actualQuantity: '980.000',
                goodQuantity: '970.000',
                rejectedQuantity: '10.000',
                runtimeMinutes: 420,
                downtimeMinutes: 20,
                achievementPercentage: 98.0,
                rejectionPercentage: 1.02,
                averageProductionRatePerHour:
                    '140.000',
                observedUtilizationPercentage:
                    95.45
            );

        return new ProductionKpiSummary(
            filter: $filter,
            generatedAt:
                CarbonImmutable::parse(
                    '2026-08-02T21:45:00Z'
                ),
            units: [
                $unit,
            ],
            recordCount: 1,
            validatedRecordCount: 1,
            provisionalRecordCount: 0,
            targetOrderCount: 1,
            runtimeMinutes: 420,
            downtimeMinutes: 20
        );
    }
}
