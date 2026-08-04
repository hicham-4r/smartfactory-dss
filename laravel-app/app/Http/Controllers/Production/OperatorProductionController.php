<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Operator\BrowseOperatorProductionRequest;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Services\Production\OperatorProductionQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class OperatorProductionController extends Controller
{
    public function index(
        BrowseOperatorProductionRequest $request,
        OperatorProductionQueryService $queries
    ): Response {
        Gate::authorize(
            'viewAny',
            ProductionOrder::class
        );

        $filters = $request->validated();

        $referenceDate =
            CarbonImmutable::createFromFormat(
                'Y-m-d',
                $filters['reference_date']
                    ?? now()->toDateString()
            )->startOfDay();

        $user = $request->user();

        return $this->privateView(
            'production.operator.index',
            [
                'operator' =>
                    $queries->operatorForOrFail(
                        $user
                    ),

                'assignments' =>
                    $queries->currentAssignments(
                        $user,
                        $referenceDate
                    ),

                'orders' =>
                    $queries->assignedOrders(
                        $user,
                        $referenceDate,
                        $filters
                    ),

                'records' =>
                    $queries->ownRecords(
                        $user,
                        $filters
                    ),

                'events' =>
                    $queries->ownEvents(
                        $user,
                        $filters
                    ),

                'filters' => $filters,

                'referenceDate' =>
                    $referenceDate,
            ]
        );
    }

    public function showOrder(
        Request $request,
        ProductionOrder $productionOrder
    ): Response {
        Gate::authorize(
            'view',
            $productionOrder
        );

        $productionOrder->load([
            'product',
            'productionLine',
            'shift',
            'batches' => fn ($query) =>
                $query
                    ->orderBy(
                        'sequence_number'
                    )
                    ->orderBy('id'),
        ]);

        return $this->privateView(
            'production.operator.orders.show',
            [
                'productionOrder' =>
                    $productionOrder,
            ]
        );
    }

    public function showBatch(
        Request $request,
        ProductionBatch $productionBatch,
        OperatorProductionQueryService $queries
    ): Response {
        Gate::authorize(
            'view',
            $productionBatch
        );

        $productionBatch->load([
            'productionOrder.product',
            'productionOrder.productionLine',
            'productionOrder.shift',
        ]);

        return $this->privateView(
            'production.operator.batches.show',
            [
                'productionBatch' =>
                    $productionBatch,

                'records' =>
                    $queries->recordsForBatch(
                        $request->user(),
                        $productionBatch
                    ),

                'events' =>
                    $queries->eventsForBatch(
                        $request->user(),
                        $productionBatch
                    ),
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