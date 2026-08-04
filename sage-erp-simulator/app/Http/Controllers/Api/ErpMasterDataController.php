<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MasterDataIndexRequest;
use App\Http\Resources\Api\MachineResource;
use App\Http\Resources\Api\OperatorAssignmentResource;
use App\Http\Resources\Api\OperatorResource;
use App\Http\Resources\Api\ProductFamilyResource;
use App\Http\Resources\Api\ProductResource;
use App\Http\Resources\Api\ProductionLineResource;
use App\Http\Resources\Api\ShiftResource;
use App\Services\ErpMasterDataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ErpMasterDataController extends Controller
{
    public function __construct(
        private readonly ErpMasterDataService $service
    ) {
    }

    public function productFamilies(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ProductFamilyResource::collection(
            $this->service->productFamilies(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function products(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ProductResource::collection(
            $this->service->products(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function productionLines(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ProductionLineResource::collection(
            $this->service->productionLines(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function machines(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return MachineResource::collection(
            $this->service->machines(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function shifts(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return ShiftResource::collection(
            $this->service->shifts(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function operators(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return OperatorResource::collection(
            $this->service->operators(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function operatorAssignments(
        MasterDataIndexRequest $request
    ): AnonymousResourceCollection {
        return OperatorAssignmentResource::collection(
            $this->service->operatorAssignments(
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
