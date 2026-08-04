<?php

namespace App\Http\Controllers\Analytics;

use App\DTOs\Analytics\QualityAnalyticsFilter;
use App\Enums\ERP\ErpFinishedLotStatus;
use App\Enums\ERP\ErpInspectionResult;
use App\Enums\ERP\ErpNonconformitySeverity;
use App\Enums\ERP\ErpNonconformityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\BrowseQualityKpiRequest;
use App\Repositories\Contracts\QualityAnalyticsRepositoryInterface;
use App\Services\Analytics\QualityKpiService;
use Illuminate\Http\Response;

final class QualityKpiController extends Controller
{
    public function index(
        BrowseQualityKpiRequest $request,
        QualityKpiService $service,
        QualityAnalyticsRepositoryInterface $repository,
    ): Response {
        $filter = QualityAnalyticsFilter::fromValidated(
            $request->validated(),
            (int) config('analytics.maximum_range_days', 366)
        );

        $timezoneOptions = config(
            'analytics.allowed_timezones',
            ['Africa/Casablanca', 'UTC']
        );
        $timezoneOptions = is_array($timezoneOptions)
            ? array_values(
                array_filter(
                    $timezoneOptions,
                    static fn (mixed $timezone): bool =>
                        is_string($timezone) && trim($timezone) !== ''
                )
            )
            : [];

        if (! in_array($filter->timezone, $timezoneOptions, true)) {
            $timezoneOptions[] = $filter->timezone;
        }

        return response()
            ->view(
                'analytics.quality.index',
                [
                    'summary' => $service->summarize($filter),
                    'filter' => $filter,
                    'productionLines' =>
                        $repository->filterableProductionLines($filter),
                    'productFamilies' =>
                        $repository->filterableProductFamilies($filter),
                    'products' =>
                        $repository->filterableProducts($filter),
                    'filterCompatibilityRows' =>
                        $repository->filterCompatibilityRows($filter),
                    'inspectionResults' => ErpInspectionResult::cases(),
                    'lotStatuses' => ErpFinishedLotStatus::cases(),
                    'nonconformitySeverities' =>
                        ErpNonconformitySeverity::cases(),
                    'nonconformityStatuses' =>
                        ErpNonconformityStatus::cases(),
                    'timezoneOptions' => $timezoneOptions,
                ]
            )
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }
}
