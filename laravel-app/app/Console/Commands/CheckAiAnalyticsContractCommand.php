<?php

namespace App\Console\Commands;

use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;
use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\ProductionKpiSummary;
use App\DTOs\Analytics\ProductionKpiUnitSummary;
use App\Services\AI\AnalyticsContractService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class CheckAiAnalyticsContractCommand extends Command
{
    protected $signature =
        'ai:analytics-contract:check';

    protected $description =
        'Validate the versioned Laravel-to-FastAPI analytics contract using a zero-valued structural sample';

    public function handle(
        AnalyticsContractService $contracts
    ): int {
        $timezone = (string) config(
            'analytics.default_timezone',
            'Africa/Casablanca'
        );

        $generatedAt =
            CarbonImmutable::now('UTC');

        $localDate = $generatedAt
            ->setTimezone($timezone)
            ->startOfDay();

        $filter = new AnalyticsFilter(
            startDate: $localDate,
            endDate: $localDate,
            timezone: $timezone
        );

        /*
         * This command verifies only structure and transport.
         * It never reads production tables and never presents these
         * zero-valued fields as operational company data.
         */
        $unit =
            new ProductionKpiUnitSummary(
                quantityUnit:
                    'contract_sample_unit',
                targetOrderCount: 0,
                recordCount: 0,
                validatedRecordCount: 0,
                provisionalRecordCount: 0,
                targetQuantity: '0.000',
                actualQuantity: '0.000',
                goodQuantity: '0.000',
                rejectedQuantity: '0.000',
                runtimeMinutes: 0,
                downtimeMinutes: 0,
                achievementPercentage: null,
                rejectionPercentage: null,
                averageProductionRatePerHour:
                    null,
                observedUtilizationPercentage:
                    null
            );

        $summary =
            new ProductionKpiSummary(
                filter: $filter,
                generatedAt: $generatedAt,
                units: [
                    $unit,
                ],
                recordCount: 0,
                validatedRecordCount: 0,
                provisionalRecordCount: 0,
                targetOrderCount: 0,
                runtimeMinutes: 0,
                downtimeMinutes: 0
            );

        $contract =
            new AnalyticsSnapshotContract(
                snapshotId:
                    (string) Str::uuid(),
                generatedAt: $generatedAt,
                timezone: $timezone,
                sourceSystem:
                    (string) config(
                        'erp-monitoring.source_system',
                        'simulated_sage'
                    ),
                productionKpis: $summary
            );

        $result = $contracts->validate(
            contract: $contract,
            requestId:
                'ai-contract-check-'
                .Str::uuid()
        );

        if (! $result->isAccepted()) {
            $this->components->error(
                $result->message
                ?? 'The analytics contract was not accepted.'
            );

            return self::FAILURE;
        }

        $this->components->success(
            'The versioned analytics contract was accepted.'
        );

        $this->table(
            [
                'Property',
                'Value',
            ],
            [
                [
                    'Contract',
                    $result->contractName,
                ],
                [
                    'Version',
                    $result->contractVersion,
                ],
                [
                    'Accepted sections',
                    implode(
                        ', ',
                        $result->acceptedSections
                    ),
                ],
                [
                    'Request ID',
                    $result->requestId
                    ?? 'not returned',
                ],
            ]
        );

        $this->components->info(
            'Only a zero-valued simulated structural sample was transmitted; no operational data was read.'
        );

        return self::SUCCESS;
    }
}
