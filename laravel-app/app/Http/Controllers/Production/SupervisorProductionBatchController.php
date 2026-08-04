<?php

namespace App\Http\Controllers\Production;

use App\DTOs\Production\CreateProductionBatchData;
use App\Enums\Production\ProductionBatchStatus;
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Supervisor\StoreSupervisorProductionBatchRequest;
use App\Http\Requests\Production\Supervisor\TransitionSupervisorProductionBatchRequest;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Services\Production\ProductionWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

final class SupervisorProductionBatchController extends Controller
{
    public function store(
        StoreSupervisorProductionBatchRequest $request,
        ProductionOrder $productionOrder,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $batch = $workflow->createBatch(
                $request->user(),
                new CreateProductionBatchData(
                    productionOrderId:
                        $productionOrder->getKey(),

                    plannedQuantity:
                        (string) $data[
                            'planned_quantity'
                        ],

                    scheduledStartAt:
                        isset(
                            $data[
                                'scheduled_start_at'
                            ]
                        )
                            ? CarbonImmutable::parse(
                                $data[
                                    'scheduled_start_at'
                                ]
                            )
                            : null,

                    quantityUnit:
                        $productionOrder
                            ->quantity_unit,
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
                'production.supervisor.batches.show',
                $batch
            )
            ->with(
                'success',
                'The production batch was created.'
            );
    }

    public function transition(
        TransitionSupervisorProductionBatchRequest $request,
        ProductionBatch $productionBatch,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $batch = $workflow->transitionBatch(
                actor: $request->user(),

                batchId:
                    $productionBatch->getKey(),

                target:
                    ProductionBatchStatus::from(
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
                'production.supervisor.batches.show',
                $batch
            )
            ->with(
                'success',
                'The production-batch status was updated.'
            );
    }
}