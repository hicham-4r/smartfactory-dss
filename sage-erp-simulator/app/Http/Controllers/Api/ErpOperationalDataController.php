<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OperationalDataIndexRequest;
use App\Http\Resources\Api\ProductionBatchResource;
use App\Http\Resources\Api\ProductionOrderResource;
use App\Http\Resources\Api\ProductionRecordResource;
use App\Services\ErpOperationalDataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ErpOperationalDataController extends Controller
{
    public function __construct(
        private readonly ErpOperationalDataService $service
    ) {
    }

    public function productionOrders(
        OperationalDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ProductionOrderResource::collection(
            $this->service->productionOrders(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function productionBatches(
        OperationalDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ProductionBatchResource::collection(
            $this->service->productionBatches(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function productionRecords(
        OperationalDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ProductionRecordResource::collection(
            $this->service->productionRecords(
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