<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MaintenanceDataIndexRequest;
use App\Http\Resources\Api\DowntimeEventResource;
use App\Http\Resources\Api\MachineStatusEventResource;
use App\Http\Resources\Api\MaintenanceHistoryResource;
use App\Services\ErpMaintenanceDataService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ErpMaintenanceDataController extends Controller
{
    public function __construct(
        private readonly ErpMaintenanceDataService $service
    ) {
    }

    public function downtimeEvents(
        MaintenanceDataIndexRequest $request
    ): AnonymousResourceCollection {
        return DowntimeEventResource::collection(
            $this->service->downtimeEvents(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function machineStatusEvents(
        MaintenanceDataIndexRequest $request
    ): AnonymousResourceCollection {
        return MachineStatusEventResource::collection(
            $this->service->machineStatusEvents(
                $request->filters()
            )
        )->additional($this->metadata());
    }

    public function maintenanceHistory(
        MaintenanceDataIndexRequest $request
    ): AnonymousResourceCollection {
        return MaintenanceHistoryResource::collection(
            $this->service->maintenanceHistory(
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