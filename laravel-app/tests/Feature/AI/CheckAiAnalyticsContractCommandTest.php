<?php

namespace Tests\Feature\AI;

use App\Contracts\AI\AiServiceClientInterface;
use App\DTOs\AI\AiServiceHealth;
use App\DTOs\AI\Analytics\AnalyticsContractValidationResult;
use App\DTOs\AI\Analytics\AnalyticsSnapshotContract;
use App\Enums\AI\AnalyticsContractValidationStatus;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

final class CheckAiAnalyticsContractCommandTest extends TestCase
{
    public function test_command_uses_only_a_structural_sample_and_reports_acceptance(): void
    {
        $this->app->instance(
            AiServiceClientInterface::class,
            new class implements
                AiServiceClientInterface
            {
                public function health(
                    string $requestId
                ): AiServiceHealth {
                    return AiServiceHealth
                        ::notConfigured();
                }

                public function validateAnalyticsContract(
                    AnalyticsSnapshotContract $contract,
                    string $requestId
                ): AnalyticsContractValidationResult {
                    Assert::assertSame(
                        [
                            'production_kpis',
                        ],
                        $contract->sectionNames()
                    );

                    Assert::assertSame(
                        '0.000',
                        $contract->toArray()[
                            'payload'
                        ][
                            'production_kpis'
                        ][
                            'units'
                        ][0][
                            'actual_quantity'
                        ]
                    );

                    return new AnalyticsContractValidationResult(
                        status:
                            AnalyticsContractValidationStatus
                                ::Accepted,
                        checkedAt:
                            CarbonImmutable::now(),
                        snapshotId:
                            $contract->snapshotId,
                        acceptedSections:
                            $contract->sectionNames(),
                        requestId: $requestId
                    );
                }
            }
        );

        $this->artisan(
            'ai:analytics-contract:check'
        )->assertSuccessful();
    }
}
