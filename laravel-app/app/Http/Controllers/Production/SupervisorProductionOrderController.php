<?php

namespace App\Http\Controllers\Production;

use App\DTOs\Production\CreateProductionOrderData;
use App\Enums\Production\ProductionOrderStatus;
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Supervisor\StoreSupervisorProductionOrderRequest;
use App\Http\Requests\Production\Supervisor\TransitionSupervisorProductionOrderRequest;
use App\Models\ProductionOrder;
use App\Services\Production\ProductionWorkflowService;
use App\Services\Production\SupervisorProductionQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class SupervisorProductionOrderController extends Controller
{
    public function create(
        SupervisorProductionQueryService $queries
    ): Response {
        Gate::authorize(
            'create',
            ProductionOrder::class
        );

        return $this->privateView(
            'production.supervisor.orders.create',
            [
                'products' =>
                    $queries->activeProducts(),

                'productionLines' =>
                    $queries
                        ->activeProductionLines(),

                'shifts' =>
                    $queries->activeShifts(),
            ]
        );
    }

    public function store(
        StoreSupervisorProductionOrderRequest $request,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $order = $workflow->createOrder(
                $request->user(),
                new CreateProductionOrderData(
                    productId:
                        (int) $data['product_id'],

                    productionLineId:
                        (int) $data[
                            'production_line_id'
                        ],

                    shiftId:
                        isset($data['shift_id'])
                            ? (int) $data['shift_id']
                            : null,

                    plannedStartAt:
                        CarbonImmutable::parse(
                            $data['planned_start_at']
                        ),

                    plannedEndAt:
                        isset(
                            $data['planned_end_at']
                        )
                            ? CarbonImmutable::parse(
                                $data[
                                    'planned_end_at'
                                ]
                            )
                            : null,

                    targetQuantity:
                        (string) $data[
                            'target_quantity'
                        ],

                    quantityUnit:
                        $data['quantity_unit'],

                    priority:
                        (int) $data['priority'],

                    instructions:
                        $data['instructions']
                            ?? null,
                )
            );
        } catch (
            ProductionWorkflowException $exception
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'workflow' =>
                        $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'production.supervisor.orders.show',
                $order
            )
            ->with(
                'success',
                'The production order was created as a draft.'
            );
    }

    public function transition(
        TransitionSupervisorProductionOrderRequest $request,
        ProductionOrder $productionOrder,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $order = $workflow->transitionOrder(
                actor: $request->user(),

                orderId:
                    $productionOrder->getKey(),

                target:
                    ProductionOrderStatus::from(
                        $data['target_status']
                    ),

                expectedVersion:
                    (int) $data[
                        'lock_version'
                    ]
            );
        } catch (
            ProductionWorkflowException
            | InvalidProductionStatusTransition
            | OptimisticLockException $exception
        ) {
            return back()->withErrors([
                'workflow' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'production.supervisor.orders.show',
                $order
            )
            ->with(
                'success',
                'The production-order status was updated.'
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