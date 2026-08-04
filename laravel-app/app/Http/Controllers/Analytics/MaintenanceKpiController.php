<?php

namespace App\Http\Controllers\Analytics;

use App\DTOs\Analytics\MaintenanceAnalyticsFilter;
use App\Enums\ERP\ErpMaintenanceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\BrowseMaintenanceKpiRequest;
use App\Repositories\Contracts\MaintenanceAnalyticsRepositoryInterface;
use App\Services\Analytics\MaintenanceKpiService;
use Illuminate\Http\Response;

final class MaintenanceKpiController extends Controller
{
    public function index(
        BrowseMaintenanceKpiRequest $request,
        MaintenanceKpiService $service,
        MaintenanceAnalyticsRepositoryInterface $repository,
    ): Response {
        $filter =
            MaintenanceAnalyticsFilter::fromValidated(
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

        $timezoneOptions =
            is_array($timezoneOptions)
                ? array_values(
                    array_filter(
                        $timezoneOptions,
                        static fn (
                            mixed $timezone
                        ): bool =>
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
            $timezoneOptions[] =
                $filter->timezone;
        }

        return response()
            ->view(
                'analytics.maintenance.index',
                [
                    'summary' =>
                        $service->summarize(
                            $filter
                        ),

                    'filter' =>
                        $filter,

                    'productionLines' =>
                        $repository
                            ->filterableProductionLines(
                                $filter
                            ),

                    'machines' =>
                        $repository
                            ->filterableMachines(
                                $filter
                            ),

                    'maintenanceTypes' =>
                        ErpMaintenanceType::cases(),

                    'downtimeCategories' => [
                        'planned' =>
                            'Planned',

                        'unplanned' =>
                            'Unplanned',
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
