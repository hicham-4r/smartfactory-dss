<?php

namespace App\Http\Controllers;

use App\DTOs\Dashboard\DashboardFilter;
use App\Http\Requests\Dashboard\BrowseDashboardRequest;
use App\Services\Dashboard\DashboardOverviewService;
use Illuminate\Http\Response;

final class DashboardController extends Controller
{
    public function index(
        BrowseDashboardRequest $request,
        DashboardOverviewService $service,
    ): Response {
        $filter = DashboardFilter::fromValidated(
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
                'dashboard',
                [
                    'overview' =>
                        $service->build(
                            $request->user(),
                            $filter
                        ),
                    'filter' => $filter,
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
