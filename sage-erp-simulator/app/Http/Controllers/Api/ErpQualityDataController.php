<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QualityDataIndexRequest;
use App\Http\Resources\Api\FinishedLotReleaseResource;
use App\Http\Resources\Api\FinishedLotResource;
use App\Http\Resources\Api\InspectionResource;
use App\Http\Resources\Api\NonconformityResource;
use App\Http\Resources\Api\QualityInspectionResource;
use App\Http\Resources\Api\QualityTestResultResource;
use App\Services\ErpQualityDataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ErpQualityDataController extends Controller
{
    public function __construct(
        private readonly ErpQualityDataService $service
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Canonical synchronization endpoints
    |--------------------------------------------------------------------------
    */

    public function finishedLots(
        QualityDataIndexRequest $request
    ): AnonymousResourceCollection {
        return FinishedLotResource::collection(
            $this->service->finishedLotReleases(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function inspections(
        QualityDataIndexRequest $request
    ): AnonymousResourceCollection {
        return InspectionResource::collection(
            $this->service->qualityInspections(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function nonconformities(
        QualityDataIndexRequest $request
    ): AnonymousResourceCollection {
        return NonconformityResource::collection(
            $this->service->nonconformities(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    /*
    |--------------------------------------------------------------------------
    | Existing detailed simulator endpoints
    |--------------------------------------------------------------------------
    */

    public function qualityInspections(
        QualityDataIndexRequest $request
    ): AnonymousResourceCollection {
        return QualityInspectionResource::collection(
            $this->service->qualityInspections(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function qualityTestResults(
        QualityDataIndexRequest $request
    ): AnonymousResourceCollection {
        return QualityTestResultResource::collection(
            $this->service->qualityTestResults(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function finishedLotReleases(
        QualityDataIndexRequest $request
    ): AnonymousResourceCollection {
        return FinishedLotReleaseResource::collection(
            $this->service->finishedLotReleases(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        return [
            'meta' => [
                'service' => 'sage-erp-simulator',
                'data_source' => 'simulated',
                'api_version' => '1.0',
                'generated_at' =>
                    now()->toIso8601String(),
            ],
        ];
    }
}
