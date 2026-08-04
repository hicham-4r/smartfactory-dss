<?php

namespace App\Services\Reports;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Reports\ProductionReport;
use App\Enums\Reports\ProductionReportType;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\ProductionOrder;
use App\Models\Shift;
use App\Models\User;
use App\Services\Analytics\ProductionBreakdownService;
use App\Services\Analytics\ProductionKpiService;
use Carbon\CarbonImmutable;

final class ProductionReportService
{
    public function __construct(
        private readonly ProductionKpiService $kpis,
        private readonly ProductionBreakdownService $breakdowns,
    ) {
    }

    public function build(
        AnalyticsFilter $filter,
        ProductionReportType $type,
        User $generatedBy
    ): ProductionReport {
        abort_unless(
            $type->canBeGeneratedBy($generatedBy),
            403
        );

        return new ProductionReport(
            type: $type,
            title: $this->title(
                $type,
                $filter
            ),
            filter: $filter,
            generatedAt:
                CarbonImmutable::now('UTC'),
            generatedByName:
                $generatedBy->name,
            generatedByEmail:
                $generatedBy->email,
            appliedFilters:
                $this->appliedFilters($filter),
            summary:
                $this->kpis->summarize($filter),
            breakdowns:
                $this->breakdowns->build($filter),
        );
    }

    private function title(
        ProductionReportType $type,
        AnalyticsFilter $filter
    ): string {
        return $type->label()
            .' - '
            .$filter->startDateString()
            .' to '
            .$filter->endDateString();
    }

    /**
     * @return array<string, string>
     */
    private function appliedFilters(
        AnalyticsFilter $filter
    ): array {
        $filters = [
            'Period' =>
                $filter->startDateString()
                .' to '
                .$filter->endDateString(),
            'Timezone' =>
                $filter->timezone,
            'Execution status' =>
                $filter->status === null
                    ? 'All execution statuses'
                    : ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $filter->status
                        )
                    ),
        ];

        if ($filter->productionLineId !== null) {
            $line = ProductionLine::query()->find(
                $filter->productionLineId
            );

            $filters['Production line'] =
                $line === null
                    ? 'Unknown'
                    : $line->code
                        .' - '
                        .$line->name;
        }

        if ($filter->productFamilyId !== null) {
            $family = ProductFamily::query()->find(
                $filter->productFamilyId
            );

            $filters['Product family'] =
                $family?->name
                ?? 'Unknown';
        }

        if ($filter->productId !== null) {
            $product = Product::query()->find(
                $filter->productId
            );

            $filters['Product'] =
                $product === null
                    ? 'Unknown'
                    : $product->code
                        .' - '
                        .$product->name;
        }

        if ($filter->shiftId !== null) {
            $shift = Shift::query()->find(
                $filter->shiftId
            );

            $filters['Shift'] =
                $shift === null
                    ? 'Unknown'
                    : $shift->code
                        .' - '
                        .$shift->name;
        }

        if ($filter->productionOrderId !== null) {
            $order = ProductionOrder::query()->find(
                $filter->productionOrderId
            );

            $filters['Production order'] =
                $order?->order_number
                ?? 'Unknown';
        }

        return $filters;
    }
}
