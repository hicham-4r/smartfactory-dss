<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Supervisor\BrowseSupervisorProductionRequest;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Services\Production\SupervisorProductionQueryService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class SupervisorProductionController extends Controller
{
    public function index(
        BrowseSupervisorProductionRequest $request,
        SupervisorProductionQueryService $queries
    ): Response {
        Gate::authorize(
            'viewAny',
            ProductionOrder::class
        );

        $filters = $request->validated();

        return $this->privateView(
            'production.supervisor.index',
            [
                'summary' =>
                    $queries->summary(),

                'orders' =>
                    $queries->orders(
                        $filters
                    ),

                'records' =>
                    $queries->pendingRecords(
                        $filters
                    ),

                'events' =>
                    $queries->unresolvedEvents(
                        $filters
                    ),

                'productionLines' =>
                    $queries
                        ->activeProductionLines(),

                'shifts' =>
                    $queries->activeShifts(),

                'orderStatuses' =>
                    $queries->orderStatuses(),

                'eventTypes' =>
                    $queries->eventTypes(),

                'eventSeverities' =>
                    $queries->eventSeverities(),

                'filters' => $filters,
            ]
        );
    }

    public function showOrder(
        ProductionOrder $productionOrder
    ): Response {
        Gate::authorize(
            'view',
            $productionOrder
        );

        $productionOrder->load([
            'product.productFamily',
            'productionLine',
            'shift',
            'creator',
            'updater',
            'batches' => fn ($query) =>
                $query
                    ->withCount([
                        'records',
                        'events',
                    ])
                    ->orderBy(
                        'sequence_number'
                    )
                    ->orderBy('id'),
        ]);

        return $this->privateView(
            'production.supervisor.orders.show',
            [
                'productionOrder' =>
                    $productionOrder,
            ]
        );
    }

    public function showBatch(
        ProductionBatch $productionBatch
    ): Response {
        Gate::authorize(
            'view',
            $productionBatch
        );

        $productionBatch->load([
            'productionOrder.product',
            'productionOrder.productionLine',
            'productionOrder.shift',
            'creator',
            'updater',
            'records' => fn ($query) =>
                $query
                    ->with([
                        'operator',
                        'shift',
                        'recordedBy',
                    ])
                    ->orderByDesc(
                        'production_date'
                    )
                    ->orderByDesc('id'),

            'events' => fn ($query) =>
                $query
                    ->with([
                        'machine',
                        'reportedBy',
                    ])
                    ->orderByDesc(
                        'started_at'
                    ),
        ]);

        return $this->privateView(
            'production.supervisor.batches.show',
            [
                'productionBatch' =>
                    $productionBatch,
            ]
        );
    }

    public function showRecord(
        ProductionRecord $productionRecord
    ): Response {
        Gate::authorize(
            'view',
            $productionRecord
        );

        $productionRecord->load([
            'productionBatch.productionOrder.product',
            'productionLine',
            'shift',
            'operator',
            'recordedBy',
            'updatedBy',
            'validations.decidedBy',
            'events.machine',
        ]);

        return $this->privateView(
            'production.supervisor.records.show',
            [
                'productionRecord' =>
                    $productionRecord,
            ]
        );
    }

    public function showEvent(
        ProductionEvent $productionEvent
    ): Response {
        Gate::authorize(
            'view',
            $productionEvent
        );

        $productionEvent->load([
            'productionBatch.productionOrder.product',
            'productionRecord',
            'productionLine',
            'machine',
            'shift',
            'operator',
            'reportedBy',
            'resolvedBy',
        ]);

        return $this->privateView(
            'production.supervisor.events.show',
            [
                'productionEvent' =>
                    $productionEvent,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function privateView(
        string $view,
        array $data
    ): Response {
        return response()
            ->view($view, $data)
            ->withHeaders([
                'Cache-Control' =>
                    'no-store, private, max-age=0',

                'Pragma' => 'no-cache',

                'Expires' => '0',
            ]);
    }
}