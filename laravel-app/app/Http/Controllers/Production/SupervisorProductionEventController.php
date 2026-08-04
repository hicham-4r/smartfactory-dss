<?php

namespace App\Http\Controllers\Production;

use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Supervisor\ResolveSupervisorProductionEventRequest;
use App\Models\ProductionEvent;
use App\Services\Production\ProductionWorkflowService;
use Illuminate\Http\RedirectResponse;

final class SupervisorProductionEventController extends Controller
{
    public function resolve(
        ResolveSupervisorProductionEventRequest $request,
        ProductionEvent $productionEvent,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $event = $workflow->resolveEvent(
                actor: $request->user(),

                eventId:
                    $productionEvent->getKey(),

                expectedVersion:
                    (int) $data[
                        'lock_version'
                    ]
            );
        } catch (
            ProductionWorkflowException
            | OptimisticLockException $exception
        ) {
            return back()->withErrors([
                'workflow' =>
                    $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route(
                'production.supervisor.events.show',
                $event
            )
            ->with(
                'success',
                'The production event was resolved.'
            );
    }
}