<?php

namespace App\Http\Controllers\Production;

use App\DTOs\Production\CreateProductionRecordData;
use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\Operator\StoreOperatorProductionRecordRequest;
use App\Http\Requests\Production\Operator\SubmitOperatorProductionRecordRequest;
use App\Models\ProductionBatch;
use App\Models\ProductionRecord;
use App\Services\Production\OperatorProductionQueryService;
use App\Services\Production\ProductionWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class OperatorProductionRecordController extends Controller
{
    public function create(
        Request $request,
        ProductionBatch $productionBatch,
        OperatorProductionQueryService $queries
    ): Response {
        Gate::authorize(
            'create',
            [
                ProductionRecord::class,
                $productionBatch,
            ]
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

        return $this->privateView(
            'production.operator.records.create',
            [
                'productionBatch' =>
                    $productionBatch,

                'assignments' =>
                    $assignments,
            ]
        );
    }

    public function store(
        StoreOperatorProductionRecordRequest $request,
        ProductionBatch $productionBatch,
        OperatorProductionQueryService $queries,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        $productionDate =
            CarbonImmutable::createFromFormat(
                'Y-m-d',
                $data['production_date']
            )->startOfDay();

        $assignment =
            $queries->resolveAssignmentForBatch(
                $request->user(),
                $productionBatch,
                (int) $data['shift_id'],
                $productionDate
            );

        try {
            $record = $workflow->createRecord(
                $request->user(),
                new CreateProductionRecordData(
                    productionBatchId:
                        $productionBatch->getKey(),

                    shiftId:
                        $assignment->shift_id,

                    operatorId:
                        $assignment->operator_id,

                    productionDate:
                        $productionDate,

                    startedAt:
                        $this->dateTimeOrNull(
                            $data['started_at']
                                ?? null
                        ),

                    endedAt:
                        $this->dateTimeOrNull(
                            $data['ended_at']
                                ?? null
                        ),

                    producedQuantity:
                        (string) $data[
                            'produced_quantity'
                        ],

                    goodQuantity:
                        (string) $data[
                            'good_quantity'
                        ],

                    rejectedQuantity:
                        (string) $data[
                            'rejected_quantity'
                        ],

                    quantityUnit:
                        $productionBatch
                            ->quantity_unit,

                    runtimeMinutes:
                        (int) $data[
                            'runtime_minutes'
                        ],

                    downtimeMinutes:
                        (int) $data[
                            'downtime_minutes'
                        ],

                    notes:
                        $data['notes'] ?? null,
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
                'production.operator.records.show',
                $record
            )
            ->with(
                'success',
                'The production record was created as a draft.'
            );
    }

    public function show(
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
            'validations.decidedBy',
            'events.machine',
        ]);

        return $this->privateView(
            'production.operator.records.show',
            [
                'productionRecord' =>
                    $productionRecord,
            ]
        );
    }

    public function submit(
        SubmitOperatorProductionRecordRequest $request,
        ProductionRecord $productionRecord,
        ProductionWorkflowService $workflow
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $record = $workflow->submitRecord(
                $request->user(),
                $productionRecord->getKey(),
                (int) $data['lock_version']
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
                'production.operator.records.show',
                $record
            )
            ->with(
                'success',
                'The production record was submitted for supervisor validation.'
            );
    }

    private function dateTimeOrNull(
        mixed $value
    ): ?CarbonImmutable {
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        return CarbonImmutable::parse(
            $value
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