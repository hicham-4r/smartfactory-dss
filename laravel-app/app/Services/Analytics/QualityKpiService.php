<?php

namespace App\Services\Analytics;

use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\DTOs\Analytics\QualityCategoryMetric;
use App\DTOs\Analytics\QualityDimensionMetric;
use App\DTOs\Analytics\QualityKpiSummary;
use App\DTOs\Analytics\QualityUnitMetric;
use App\Enums\Analytics\QualityBreakdownDimension;
use App\Repositories\Contracts\QualityAnalyticsRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class QualityKpiService
{
    public function __construct(
        private QualityAnalyticsRepositoryInterface $repository,
        private QualityKpiFormulaService $formula,
    ) {
    }

    public function summarize(
        QualityAnalyticsFilter $filter
    ): QualityKpiSummary {
        $inspections = $this->repository->inspectionTotals($filter);
        $lots = $this->repository->lotTotals($filter);
        $nonconformities =
            $this->repository->nonconformityTotals($filter);

        $inspectionCount = $this->integer(
            $inspections->inspection_count ?? 0
        );
        $passedInspectionCount = $this->integer(
            $inspections->passed_inspection_count ?? 0
        );
        $failedSampleQuantity = $this->integer(
            $inspections->failed_sample_quantity ?? 0
        );
        $sampleSize = $this->integer($inspections->sample_size ?? 0);
        $lotCount = $this->integer($lots->lot_count ?? 0);
        $releasedLotCount = $this->integer(
            $lots->released_lot_count ?? 0
        );
        $heldRejectedLotCount =
            $this->integer($lots->blocked_lot_count ?? 0)
            + $this->integer($lots->rejected_lot_count ?? 0);
        $nonconformityCount = $this->integer(
            $nonconformities->nonconformity_count ?? 0
        );

        return new QualityKpiSummary(
            filter: $filter,
            generatedAt: CarbonImmutable::now('UTC'),
            inspectionCount: $inspectionCount,
            passedInspectionCount: $passedInspectionCount,
            failedInspectionCount: $this->integer(
                $inspections->failed_inspection_count ?? 0
            ),
            conditionalInspectionCount: $this->integer(
                $inspections->conditional_inspection_count ?? 0
            ),
            pendingInspectionCount: $this->integer(
                $inspections->pending_inspection_count ?? 0
            ),
            sampleSize: $sampleSize,
            passedSampleQuantity: $this->integer(
                $inspections->passed_sample_quantity ?? 0
            ),
            failedSampleQuantity: $failedSampleQuantity,
            lotCount: $lotCount,
            releasedLotCount: $releasedLotCount,
            blockedLotCount: $this->integer(
                $lots->blocked_lot_count ?? 0
            ),
            rejectedLotCount: $this->integer(
                $lots->rejected_lot_count ?? 0
            ),
            pendingLotCount: $this->integer(
                $lots->pending_lot_count ?? 0
            ),
            nonconformityCount: $nonconformityCount,
            openNonconformityCount: $this->integer(
                $nonconformities->open_nonconformity_count ?? 0
            ),
            resolvedNonconformityCount: $this->integer(
                $nonconformities->resolved_nonconformity_count ?? 0
            ),
            minorNonconformityCount: $this->integer(
                $nonconformities->minor_nonconformity_count ?? 0
            ),
            majorNonconformityCount: $this->integer(
                $nonconformities->major_nonconformity_count ?? 0
            ),
            criticalNonconformityCount: $this->integer(
                $nonconformities->critical_nonconformity_count ?? 0
            ),
            inspectionPassPercentage: $this->formula->percentage(
                $passedInspectionCount,
                $inspectionCount
            ),
            sampleFailurePercentage: $this->formula->percentage(
                $failedSampleQuantity,
                $sampleSize
            ),
            releasedLotPercentage: $this->formula->percentage(
                $releasedLotCount,
                $lotCount
            ),
            heldRejectedLotPercentage: $this->formula->percentage(
                $heldRejectedLotCount,
                $lotCount
            ),
            nonconformitiesPer100Inspections: $this->formula->per100(
                $nonconformityCount,
                $inspectionCount
            ),
            quantityUnits: $this->quantityUnits($filter),
            byProductionLine: $this->dimensionMetrics(
                $filter,
                QualityBreakdownDimension::ProductionLine
            ),
            byProduct: $this->dimensionMetrics(
                $filter,
                QualityBreakdownDimension::Product
            ),
            byProductFamily: $this->dimensionMetrics(
                $filter,
                QualityBreakdownDimension::ProductFamily
            ),
            nonconformityCategories: $this->categoryMetrics($filter),
        );
    }

    /** @return list<QualityUnitMetric> */
    private function quantityUnits(
        QualityAnalyticsFilter $filter
    ): array {
        return array_map(
            function (object $row): QualityUnitMetric {
                $produced = $this->decimal(
                    $row->produced_quantity ?? 0
                );
                $released = $this->decimal(
                    $row->released_quantity ?? 0
                );
                $rejected = $this->decimal(
                    $row->rejected_quantity ?? 0
                );

                return new QualityUnitMetric(
                    quantityUnit: (string) $row->quantity_unit,
                    lotCount: $this->integer($row->lot_count ?? 0),
                    producedQuantity:
                        $this->formula->decimalString($produced),
                    releasedQuantity:
                        $this->formula->decimalString($released),
                    rejectedQuantity:
                        $this->formula->decimalString($rejected),
                    releasedQuantityPercentage:
                        $this->formula->percentage($released, $produced),
                    rejectedQuantityPercentage:
                        $this->formula->percentage($rejected, $produced),
                );
            },
            $this->repository->lotQuantitiesByUnit($filter)
        );
    }

    /** @return list<QualityDimensionMetric> */
    private function dimensionMetrics(
        QualityAnalyticsFilter $filter,
        QualityBreakdownDimension $dimension,
    ): array {
        $metrics = [];

        foreach (
            $this->repository->inspectionsByDimension(
                $filter,
                $dimension
            ) as $row
        ) {
            $key = (string) $row->dimension_id;
            $metrics[$key] = [
                'key' => $key,
                'label' => (string) $row->dimension_label,
                'inspection_count' =>
                    $this->integer($row->inspection_count ?? 0),
                'passed_inspection_count' =>
                    $this->integer($row->passed_inspection_count ?? 0),
                'failed_inspection_count' =>
                    $this->integer($row->failed_inspection_count ?? 0),
                'conditional_inspection_count' =>
                    $this->integer(
                        $row->conditional_inspection_count ?? 0
                    ),
                'pending_inspection_count' =>
                    $this->integer($row->pending_inspection_count ?? 0),
            ];
        }

        foreach (
            $this->repository->lotsByDimension($filter, $dimension)
            as $row
        ) {
            $key = (string) $row->dimension_id;
            $metrics[$key] ??= [
                'key' => $key,
                'label' => (string) $row->dimension_label,
            ];
            $metrics[$key] += [
                'inspection_count' => 0,
                'passed_inspection_count' => 0,
                'failed_inspection_count' => 0,
                'conditional_inspection_count' => 0,
                'pending_inspection_count' => 0,
            ];
            $metrics[$key]['lot_count'] =
                $this->integer($row->lot_count ?? 0);
            $metrics[$key]['released_lot_count'] =
                $this->integer($row->released_lot_count ?? 0);
            $metrics[$key]['blocked_lot_count'] =
                $this->integer($row->blocked_lot_count ?? 0);
            $metrics[$key]['rejected_lot_count'] =
                $this->integer($row->rejected_lot_count ?? 0);
            $metrics[$key]['pending_lot_count'] =
                $this->integer($row->pending_lot_count ?? 0);
        }

        foreach (
            $this->repository->nonconformitiesByDimension(
                $filter,
                $dimension
            ) as $row
        ) {
            $key = (string) $row->dimension_id;
            $metrics[$key] ??= [
                'key' => $key,
                'label' => (string) $row->dimension_label,
            ];
            $metrics[$key]['nonconformity_count'] =
                $this->integer($row->nonconformity_count ?? 0);
            $metrics[$key]['open_nonconformity_count'] =
                $this->integer($row->open_nonconformity_count ?? 0);
            $metrics[$key]['resolved_nonconformity_count'] =
                $this->integer($row->resolved_nonconformity_count ?? 0);
        }

        $result = [];

        foreach ($metrics as $metric) {
            $inspectionCount = $metric['inspection_count'] ?? 0;
            $lotCount = $metric['lot_count'] ?? 0;
            $nonconformityCount = $metric['nonconformity_count'] ?? 0;

            $result[] = new QualityDimensionMetric(
                key: $metric['key'],
                label: $metric['label'],
                inspectionCount: $inspectionCount,
                passedInspectionCount:
                    $metric['passed_inspection_count'] ?? 0,
                failedInspectionCount:
                    $metric['failed_inspection_count'] ?? 0,
                conditionalInspectionCount:
                    $metric['conditional_inspection_count'] ?? 0,
                pendingInspectionCount:
                    $metric['pending_inspection_count'] ?? 0,
                lotCount: $lotCount,
                releasedLotCount: $metric['released_lot_count'] ?? 0,
                blockedLotCount: $metric['blocked_lot_count'] ?? 0,
                rejectedLotCount: $metric['rejected_lot_count'] ?? 0,
                pendingLotCount: $metric['pending_lot_count'] ?? 0,
                nonconformityCount: $nonconformityCount,
                openNonconformityCount:
                    $metric['open_nonconformity_count'] ?? 0,
                resolvedNonconformityCount:
                    $metric['resolved_nonconformity_count'] ?? 0,
                inspectionPassPercentage: $this->formula->percentage(
                    $metric['passed_inspection_count'] ?? 0,
                    $inspectionCount
                ),
                releasedLotPercentage: $this->formula->percentage(
                    $metric['released_lot_count'] ?? 0,
                    $lotCount
                ),
                nonconformitiesPer100Inspections:
                    $this->formula->per100(
                        $nonconformityCount,
                        $inspectionCount
                    ),
            );
        }

        usort(
            $result,
            static fn (
                QualityDimensionMetric $left,
                QualityDimensionMetric $right
            ): int => strcasecmp($left->label, $right->label)
        );

        return $result;
    }

    /** @return list<QualityCategoryMetric> */
    private function categoryMetrics(
        QualityAnalyticsFilter $filter
    ): array {
        return array_map(
            fn (object $row): QualityCategoryMetric =>
                new QualityCategoryMetric(
                    category: (string) $row->category,
                    nonconformityCount: $this->integer(
                        $row->nonconformity_count ?? 0
                    ),
                    openCount: $this->integer($row->open_count ?? 0),
                    resolvedCount: $this->integer(
                        $row->resolved_count ?? 0
                    ),
                    minorCount: $this->integer($row->minor_count ?? 0),
                    majorCount: $this->integer($row->major_count ?? 0),
                    criticalCount: $this->integer(
                        $row->critical_count ?? 0
                    ),
                ),
            $this->repository->nonconformityCategories($filter)
        );
    }

    private function integer(mixed $value): int
    {
        return (int) ($value ?? 0);
    }

    private function decimal(mixed $value): float
    {
        return (float) ($value ?? 0);
    }
}
