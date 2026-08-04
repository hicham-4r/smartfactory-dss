<?php

namespace App\Http\Controllers\Production;

use App\Enums\Production\ProductionValidationDecision;
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Supervisor\DecideSupervisorProductionRecordRequest;
use App\Models\ProductionRecord;
use App\Services\Production\ProductionWorkflowService;
use Illuminate\Http\RedirectResponse;

final class SupervisorProductionRecordController extends Controller
{
    public function decide(
        DecideSupervisorProductionRecordRequest $request,
        ProductionRecord $productionRecord,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $record = $workflow->decideRecord(
                actor: $request->user(),

                recordId:
                    $productionRecord->getKey(),

                decision:
                    ProductionValidationDecision::from(
                        $data['decision']
                    ),

                expectedVersion:
                    (int) $data[
                        'lock_version'
                    ],

                reason:
                    $data['reason'] ?? null
            );
        } catch (
            ProductionWorkflowException
            | InvalidProductionStatusTransition
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
                'production.supervisor.records.show',
                $record
            )
            ->with(
                'success',
                'The production-record decision was saved.'
            );
    }
}