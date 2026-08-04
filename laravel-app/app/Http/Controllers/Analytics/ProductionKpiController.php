<?php

namespace App\Http\Controllers\Analytics;

use App\DTOs\Analytics\AnalyticsFilter;
use App\Enums\Production\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\BrowseProductionKpiRequest;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use App\Services\Analytics\ProductionBreakdownService;
use App\Services\Analytics\ProductionKpiService;
use Illuminate\Http\Response;

final class ProductionKpiController extends Controller
{
    public function index(
        BrowseProductionKpiRequest $request,
        ProductionKpiService $service,
        ProductionBreakdownService $breakdownService,
        ProductionAnalyticsRepositoryInterface $analyticsRepository,
    ): Response {
        $filter = AnalyticsFilter::fromValidated(
            $request->validated(),
            (int) config(
                'analytics.maximum_range_days',
                366
            )
        );

        $timezoneOptions = config(
            'analytics.allowed_timezones',
            [
                'Africa/Casablanca',
                'UTC',
            ]
        );

        $timezoneOptions = is_array(
            $timezoneOptions
        )
            ? array_values(
                array_filter(
                    $timezoneOptions,
                    static fn (mixed $timezone): bool =>
                        is_string($timezone)
                        && trim($timezone) !== ''
                )
            )
            : [];

        if (! in_array(
            $filter->timezone,
            $timezoneOptions,
            true
        )) {
            $timezoneOptions[] = $filter->timezone;
        }

        return response()
            ->view(
                'analytics.production.index',
                [
                    'summary' =>
                        $service->summarize($filter),

                    'breakdownReport' =>
                        $breakdownService->build($filter),

                    'filter' => $filter,

                    'productFamilies' =>
                        $analyticsRepository
                            ->filterableProductFamilies(
                                $filter
                            ),

                    'products' =>
                        $analyticsRepository
                            ->filterableProducts(
                                $filter
                            ),

                    'productionLines' =>
                        $analyticsRepository
                            ->filterableProductionLines(
                                $filter
                            ),

                    'shifts' =>
                        $analyticsRepository
                            ->filterableShifts(
                                $filter
                            ),

                    'productionOrders' =>
                        $analyticsRepository
                            ->filterableProductionOrders(
                                $filter
                            ),

                    'filterCompatibilityRows' =>
                        $analyticsRepository
                            ->filterCompatibilityRows(
                                $filter
                            ),

                    /*
                     * This page is an execution KPI dashboard. Draft, planned,
                     * released and cancelled work does not provide actual
                     * execution KPIs, so it is intentionally excluded here.
                     */
                    'orderStatuses' => [
                        ProductionOrderStatus::InProgress,
                        ProductionOrderStatus::Completed,
                    ],

                    'timezoneOptions' =>
                        $timezoneOptions,
                ]
            )
            ->withHeaders([
                'Cache-Control' =>
                    'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }
}
