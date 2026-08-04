<?php

namespace App\Http\Controllers\Production;

use App\DTOs\Production\CreateProductionEventData;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Operator\StoreOperatorProductionEventRequest;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Services\Production\OperatorProductionQueryService;
use App\Services\Production\ProductionWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class OperatorProductionEventController extends Controller
{
    public function create(
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

        $assignments =
            $queries->eligibleAssignmentsForBatch(
                $request->user(),
                $productionBatch,
                CarbonImmutable::today()
            );

        abort_if(
            $assignments->isEmpty(),
            403,
            'You do not have an active assignment for this batch.'
        );

        $eventTypes = $this
            ->operatorEventTypes()
            ->filter(
                fn (
                    ProductionEventType $type
                ): bool =>
                    Gate::forUser(
                        $request->user()
                    )->allows(
                        'report',
                        [
                            ProductionEvent::class,
                            $type,
                        ]
                    )
            )
            ->values();

        abort_if(
            $eventTypes->isEmpty(),
            403,
            'You are not authorized to report production events.'
        );

        return $this->privateView(
            'production.operator.events.create',
            [
                'productionBatch' =>
                    $productionBatch,

                'assignments' =>
                    $assignments,

                'machines' =>
                    $queries->machinesForBatch(
                        $productionBatch
                    ),

                'records' =>
                    $queries
                        ->selectableRecordsForEvent(
                            $request->user(),
                            $productionBatch
                        ),

                'eventTypes' =>
                    $eventTypes,

                'severities' =>
                    ProductionEventSeverity::cases(),
            ]
        );
    }

    public function store(
        StoreOperatorProductionEventRequest $request,
        ProductionBatch $productionBatch,
        OperatorProductionQueryService $queries,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        $type = ProductionEventType::from(
            $data['event_type']
        );

        Gate::authorize(
            'report',
            [
                ProductionEvent::class,
                $type,
            ]
        );

        $startedAt =
            CarbonImmutable::parse(
                $data['started_at']
            );

        $assignment =
            $queries->resolveAssignmentForBatch(
                $request->user(),
                $productionBatch,
                (int) $data['shift_id'],
                $startedAt
            );

        try {
            $event = $workflow->createEvent(
                $request->user(),
                new CreateProductionEventData(
                    productionBatchId:
                        $productionBatch->getKey(),

                    productionRecordId:
                        isset(
                            $data[
                                'production_record_id'
                            ]
                        )
                            ? (int) $data[
                                'production_record_id'
                            ]
                            : null,

                    machineId:
                        isset($data['machine_id'])
                            ? (int) $data[
                                'machine_id'
                            ]
                            : null,

                    shiftId:
                        $assignment->shift_id,

                    operatorId:
                        $assignment->operator_id,

                    eventType:
                        $type,

                    severity:
                        ProductionEventSeverity::from(
                            $data['severity']
                        ),

                    title:
                        $data['title'],

                    description:
                        $data['description']
                            ?? null,

                    startedAt:
                        $startedAt,

                    endedAt:
                        isset($data['ended_at'])
                            ? CarbonImmutable::parse(
                                $data['ended_at']
                            )
                            : null,
                )
            );
        } catch (
            ProductionWorkflowException
            | OptimisticLockException $exception
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
                'production.operator.events.show',
                $event
            )
            ->with(
                'success',
                'The production event was reported successfully.'
            );
    }

    public function show(
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
            'production.operator.events.show',
            [
                'productionEvent' =>
                    $productionEvent,
            ]
        );
    }

    /**
     * @return Collection<int, ProductionEventType>
     */
    private function operatorEventTypes(): Collection
    {
        return collect([
            ProductionEventType::Downtime,
            ProductionEventType::MachineIncident,
            ProductionEventType::Comment,
        ]);
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