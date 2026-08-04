<?php

namespace App\Http\Controllers\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\BrowseMaintenanceKpiRequest;
use App\Http\Requests\Analytics\BrowseProductionKpiRequest;
use App\Http\Requests\Analytics\BrowseQualityKpiRequest;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\ProductionOrder;
use App\Models\Shift;
use App\Services\Analytics\MaintenanceKpiService;
use App\Services\Analytics\ProductionBreakdownService;
use App\Services\Analytics\ProductionKpiService;
use App\Services\Analytics\QualityKpiService;
use Illuminate\Http\Response;

final class AnalyticsDrilldownController extends Controller
{
    public function __construct(
        private readonly ProductionKpiService $productionKpis,
        private readonly ProductionBreakdownService $productionBreakdowns,
        private readonly MaintenanceKpiService $maintenanceKpis,
        private readonly QualityKpiService $qualityKpis,
    ) {
    }

    public function productionLine(
        BrowseProductionKpiRequest $request,
        ProductionLine $productionLine
    ): Response {
        return $this->productionResponse(
            request: $request,
            forcedFilters: [
                'production_line_id' =>
                    (int) $productionLine->getKey(),
            ],
            entityType: 'Production line',
            entityLabel: $productionLine->name,
            entityCode: $productionLine->code,
        );
    }

    public function productionShift(
        BrowseProductionKpiRequest $request,
        Shift $shift
    ): Response {
        return $this->productionResponse(
            request: $request,
            forcedFilters: [
                'shift_id' => (int) $shift->getKey(),
            ],
            entityType: 'Production shift',
            entityLabel: $shift->name,
            entityCode: $shift->code,
        );
    }

    public function productionProduct(
        BrowseProductionKpiRequest $request,
        Product $product
    ): Response {
        return $this->productionResponse(
            request: $request,
            forcedFilters: [
                'product_id' => (int) $product->getKey(),
                'product_family_id' =>
                    (int) $product->product_family_id,
            ],
            entityType: 'Product',
            entityLabel: $product->name,
            entityCode: $product->code,
        );
    }

    public function productionProductFamily(
        BrowseProductionKpiRequest $request,
        ProductFamily $productFamily
    ): Response {
        return $this->productionResponse(
            request: $request,
            forcedFilters: [
                'product_family_id' =>
                    (int) $productFamily->getKey(),
            ],
            entityType: 'Product family',
            entityLabel: $productFamily->name,
            entityCode: $productFamily->code,
        );
    }

    public function productionOrder(
        BrowseProductionKpiRequest $request,
        ProductionOrder $productionOrder
    ): Response {
        return $this->productionResponse(
            request: $request,
            forcedFilters: [
                'production_order_id' =>
                    (int) $productionOrder->getKey(),
            ],
            entityType: 'Production order',
            entityLabel: $productionOrder->order_number,
            entityCode: null,
        );
    }

    public function maintenanceMachine(
        BrowseMaintenanceKpiRequest $request,
        Machine $machine
    ): Response {
        $validated = $request->validated();

        $validated['machine_id'] =
            (int) $machine->getKey();

        $validated['production_line_id'] =
            (int) $machine->production_line_id;

        $filter = MaintenanceAnalyticsFilter::fromValidated(
            $validated,
            $this->maximumRangeDays()
        );

        $machine->loadMissing('productionLine');

        return $this->drilldownResponse([
            'domain' => 'maintenance',
            'context' => [
                'eyebrow' => 'Maintenance drill-down',
                'title' => 'Machine maintenance detail',
                'entity_type' => 'Machine',
                'entity_label' => $machine->name,
                'entity_code' => $machine->code,
                'description' =>
                    'A deterministic maintenance view restricted to the selected machine and reporting period.',
            ],
            'filter' => $filter,
            'maintenanceSummary' =>
                $this->maintenanceKpis->summarize($filter),
            'analyticsUrl' => route(
                'analytics.maintenance.index',
                $this->query($filter->toArray())
            ),
            'dashboardUrl' => route(
                'dashboard',
                $this->query([
                    'start_date' =>
                        $filter->startDateString(),
                    'end_date' =>
                        $filter->endDateString(),
                    'timezone' => $filter->timezone,
                    'production_line_id' =>
                        $filter->productionLineId,
                    'machine_id' => $filter->machineId,
                    'maintenance_type' =>
                        $filter->maintenanceType,
                    'downtime_category' =>
                        $filter->downtimeCategory,
                ])
            ),
        ]);
    }

    public function qualityProductionLine(
        BrowseQualityKpiRequest $request,
        ProductionLine $productionLine
    ): Response {
        return $this->qualityResponse(
            request: $request,
            forcedFilters: [
                'production_line_id' =>
                    (int) $productionLine->getKey(),
            ],
            entityType: 'Production line',
            entityLabel: $productionLine->name,
            entityCode: $productionLine->code,
        );
    }

    public function qualityProduct(
        BrowseQualityKpiRequest $request,
        Product $product
    ): Response {
        return $this->qualityResponse(
            request: $request,
            forcedFilters: [
                'product_id' => (int) $product->getKey(),
                'product_family_id' =>
                    (int) $product->product_family_id,
            ],
            entityType: 'Product',
            entityLabel: $product->name,
            entityCode: $product->code,
        );
    }

    public function qualityProductFamily(
        BrowseQualityKpiRequest $request,
        ProductFamily $productFamily
    ): Response {
        return $this->qualityResponse(
            request: $request,
            forcedFilters: [
                'product_family_id' =>
                    (int) $productFamily->getKey(),
            ],
            entityType: 'Product family',
            entityLabel: $productFamily->name,
            entityCode: $productFamily->code,
        );
    }

    /**
     * @param array<string, int|string|null> $forcedFilters
     */
    private function productionResponse(
        BrowseProductionKpiRequest $request,
        array $forcedFilters,
        string $entityType,
        string $entityLabel,
        ?string $entityCode
    ): Response {
        $validated = array_replace(
            $request->validated(),
            $forcedFilters
        );

        $filter = AnalyticsFilter::fromValidated(
            $validated,
            $this->maximumRangeDays()
        );

        return $this->drilldownResponse([
            'domain' => 'production',
            'context' => [
                'eyebrow' => 'Production drill-down',
                'title' => $entityType.' performance detail',
                'entity_type' => $entityType,
                'entity_label' => $entityLabel,
                'entity_code' => $entityCode,
                'description' =>
                    'Targets, eligible production, quality yield, runtime and downtime remain restricted to this entity and period.',
            ],
            'filter' => $filter,
            'productionSummary' =>
                $this->productionKpis->summarize($filter),
            'productionBreakdown' =>
                $this->productionBreakdowns->build($filter),
            'analyticsUrl' => route(
                'analytics.production.index',
                $this->query($filter->toArray())
            ),
            'dashboardUrl' => route(
                'dashboard',
                $this->query([
                    'start_date' =>
                        $filter->startDateString(),
                    'end_date' =>
                        $filter->endDateString(),
                    'timezone' => $filter->timezone,
                    'production_line_id' =>
                        $filter->productionLineId,
                    'product_id' => $filter->productId,
                    'shift_id' => $filter->shiftId,
                    'status' => $filter->status,
                ])
            ),
        ]);
    }

    /**
     * @param array<string, int|string|null> $forcedFilters
     */
    private function qualityResponse(
        BrowseQualityKpiRequest $request,
        array $forcedFilters,
        string $entityType,
        string $entityLabel,
        ?string $entityCode
    ): Response {
        $validated = array_replace(
            $request->validated(),
            $forcedFilters
        );

        $filter = QualityAnalyticsFilter::fromValidated(
            $validated,
            $this->maximumRangeDays()
        );

        return $this->drilldownResponse([
            'domain' => 'quality',
            'context' => [
                'eyebrow' => 'Quality drill-down',
                'title' => $entityType.' quality detail',
                'entity_type' => $entityType,
                'entity_label' => $entityLabel,
                'entity_code' => $entityCode,
                'description' =>
                    'Inspections, finished lots and nonconformities are restricted to this entity and period.',
            ],
            'filter' => $filter,
            'qualitySummary' =>
                $this->qualityKpis->summarize($filter),
            'analyticsUrl' => route(
                'analytics.quality.index',
                $this->query($filter->toArray())
            ),
            'dashboardUrl' => route(
                'dashboard',
                $this->query([
                    'start_date' =>
                        $filter->startDateString(),
                    'end_date' =>
                        $filter->endDateString(),
                    'timezone' => $filter->timezone,
                    'production_line_id' =>
                        $filter->productionLineId,
                    'product_id' => $filter->productId,
                ])
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function drilldownResponse(
        array $data
    ): Response {
        return response()
            ->view(
                'analytics.drilldown.show',
                $data
            )
            ->withHeaders([
                'Cache-Control' =>
                    'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }

    private function maximumRangeDays(): int
    {
        return (int) config(
            'analytics.maximum_range_days',
            366
        );
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function query(
        array $values
    ): array {
        return array_filter(
            $values,
            static fn (mixed $value): bool =>
                $value !== null
                && $value !== ''
        );
    }
}
