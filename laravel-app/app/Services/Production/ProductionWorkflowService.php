<?php

namespace App\Services\Production;

use App\DTOs\Production\CreateProductionBatchData;
use App\DTOs\Production\CreateProductionEventData;
use App\DTOs\Production\CreateProductionOrderData;
use App\DTOs\Production\CreateProductionRecordData;
use App\Enums\AuditAction;
use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionImportStatus;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationDecision;
use App\Enums\Production\ProductionValidationStatus;
use App\Exceptions\Production\OptimisticLockException;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionLine;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\ProductionRecordValidation;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\ProductionExecutionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductionWorkflowService
{
    public function __construct(
        private readonly ProductionExecutionRepositoryInterface
            $repository,
        private readonly ProductionWorkflowAuthorizer
            $authorizer,
        private readonly AuditLogService
            $auditLogService,
    ) {
    }

    /**
     * Create one manual production order in draft state.
     */
    public function createOrder(
        User $actor,
        CreateProductionOrderData $data
    ): ProductionOrder {
        $this->authorizer->assertCanManageOrders(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $data
            ): ProductionOrder {
                $product = Product::query()
                    ->findOrFail($data->productId);

                $line = ProductionLine::query()
                    ->findOrFail(
                        $data->productionLineId
                    );

                $this->assertActive(
                    $product,
                    'product'
                );

                $this->assertActive(
                    $line,
                    'production line'
                );

                if ($data->shiftId !== null) {
                    $shift = Shift::query()
                        ->findOrFail($data->shiftId);

                    $this->assertActive(
                        $shift,
                        'shift'
                    );
                }

                if (
                    $data->plannedEndAt !== null
                    && $data->plannedEndAt
                        ->lessThanOrEqualTo(
                            $data->plannedStartAt
                        )
                ) {
                    throw new ProductionWorkflowException(
                        'The planned end must be later than the planned start.'
                    );
                }

                if (
                    $this->quantityToMilliUnits(
                        $data->targetQuantity
                    ) <= 0
                ) {
                    throw new ProductionWorkflowException(
                        'The production target must be greater than zero.'
                    );
                }

                if (
                    $data->priority < 1
                    || $data->priority > 5
                ) {
                    throw new ProductionWorkflowException(
                        'Production-order priority must be between 1 and 5.'
                    );
                }

                $quantityUnit = $this->requiredText(
                    $data->quantityUnit,
                    'Quantity unit',
                    30
                );

                $order = new ProductionOrder();

                $order->fill([
                    'order_number' =>
                        $this->generateNumber('PO'),

                    'product_id' =>
                        $product->getKey(),

                    'production_line_id' =>
                        $line->getKey(),

                    'shift_id' =>
                        $data->shiftId,

                    'planned_start_at' =>
                        $data->plannedStartAt,

                    'planned_end_at' =>
                        $data->plannedEndAt,

                    'target_quantity' =>
                        $data->targetQuantity,

                    'quantity_unit' =>
                        $quantityUnit,

                    'priority' =>
                        $data->priority,

                    'instructions' =>
                        $this->nullableText(
                            $data->instructions,
                            5000
                        ),
                ]);

                $order->forceFill([
                    'status' =>
                        ProductionOrderStatus::Draft,

                    'created_by' =>
                        $actor->getKey(),

                    'updated_by' =>
                        $actor->getKey(),

                    'lock_version' => 1,

                    'source_system' => 'manual',

                    'import_status' =>
                        ProductionImportStatus
                            ::NotApplicable,
                ])->save();

                $this->audit(
                    action:
                        AuditAction
                            ::ProductionOrderCreated,

                    actor: $actor,
                    auditable: $order,

                    newValues: [
                        'order_number' =>
                            $order->order_number,

                        'product_id' =>
                            $order->product_id,

                        'production_line_id' =>
                            $order
                                ->production_line_id,

                        'shift_id' =>
                            $order->shift_id,

                        'status' =>
                            $order->status->value,

                        'target_quantity' =>
                            $order->target_quantity,

                        'quantity_unit' =>
                            $order->quantity_unit,
                    ]
                );

                return $order->refresh();
            },
            attempts: 3
        );
    }

    /**
     * Change a production-order status.
     */
    public function transitionOrder(
        User $actor,
        int $orderId,
        ProductionOrderStatus $target,
        int $expectedVersion
    ): ProductionOrder {
        $this->authorizer->assertCanManageOrders(
            $actor
        );

        return DB::transaction(
            function () use (
                $actor,
                $orderId,
                $target,
                $expectedVersion
            ): ProductionOrder {
                $order = ProductionOrder::query()
                    ->with([
                        'product',
                        'productionLine',
                        'batches',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($orderId);

                $this->assertExpectedVersion(
                    $order,
                    $expectedVersion
                );

                $this->validateOrderTransition(
                    $order,
                    $target
                );

                $previousStatus =
                    $order->status;

                $updated =
                    $this->repository->updateOrder(
                        order: $order,
                        changes: [
                            'status' => $target,
                            'updated_by' =>
                                $actor->getKey(),
                        ],
                        expectedVersion:
                            $expectedVersion
                    );

                $this->auditStatusChange(
                    AuditAction
                        ::ProductionOrderStatusChanged,
                    $actor,
                    $updated,
                    $previousStatus->value,
                    $updated->status->value
                );

                return $updated;
            },
            attempts: 3
        );
    }

    /**
     * Create the next batch sequence for a released order.
     */
    public function createBatch(
        User $actor,
        CreateProductionBatchData $data
    ): ProductionBatch {
        $this->authorizer
            ->assertCanManageBatches($actor);

        return DB::transaction(
            function () use (
                $actor,
                $data
            ): ProductionBatch {
                $order = ProductionOrder::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $data->productionOrderId
                    );

                if (
                    ! in_array(
                        $order->status,
                        [
                            ProductionOrderStatus
                                ::Released,

                            ProductionOrderStatus
                                ::InProgress,
                        ],
                        true
                    )
                ) {
                    throw new ProductionWorkflowException(
                        'Batches may be created only for released or in-progress orders.'
                    );
                }

                $plannedMilli =
                    $this->quantityToMilliUnits(
                        $data->plannedQuantity
                    );

                if ($plannedMilli <= 0) {
                    throw new ProductionWorkflowException(
                        'The batch planned quantity must be greater than zero.'
                    );
                }

                $quantityUnit = $this->requiredText(
                    $data->quantityUnit,
                    'Quantity unit',
                    30
                );

                if (
                    $quantityUnit
                    !== $order->quantity_unit
                ) {
                    throw new ProductionWorkflowException(
                        'The batch quantity unit must match the production order.'
                    );
                }

                $existingPlannedMilli =
                    ProductionBatch::query()
                        ->where(
                            'production_order_id',
                            $order->getKey()
                        )
                        ->get([
                            'planned_quantity',
                        ])
                        ->sum(
                            fn (
                                ProductionBatch $batch
                            ): int =>
                                $this
                                    ->quantityToMilliUnits(
                                        $batch
                                            ->planned_quantity
                                    )
                        );

                $targetMilli =
                    $this->quantityToMilliUnits(
                        $order->target_quantity
                    );

                if (
                    $existingPlannedMilli
                    + $plannedMilli
                    > $targetMilli
                ) {
                    throw new ProductionWorkflowException(
                        'The cumulative batch quantity exceeds the production-order target.'
                    );
                }

                if (
                    $data->scheduledStartAt !== null
                    && $data->scheduledStartAt
                        ->lessThan(
                            $order->planned_start_at
                        )
                ) {
                    throw new ProductionWorkflowException(
                        'The batch cannot be scheduled before the production order.'
                    );
                }

                if (
                    $data->scheduledStartAt !== null
                    && $order->planned_end_at !== null
                    && $data->scheduledStartAt
                        ->greaterThan(
                            $order->planned_end_at
                        )
                ) {
                    throw new ProductionWorkflowException(
                        'The batch cannot be scheduled after the production-order window.'
                    );
                }

                $sequence = (
                    (int) ProductionBatch::query()
                        ->where(
                            'production_order_id',
                            $order->getKey()
                        )
                        ->max('sequence_number')
                ) + 1;

                $batch = new ProductionBatch();

                $batch->fill([
                    'production_order_id' =>
                        $order->getKey(),

                    'batch_number' =>
                        $this->generateNumber('BAT'),

                    'sequence_number' =>
                        $sequence,

                    'planned_quantity' =>
                        $data->plannedQuantity,

                    'actual_good_quantity' =>
                        '0.000',

                    'actual_rejected_quantity' =>
                        '0.000',

                    'quantity_unit' =>
                        $quantityUnit,

                    'scheduled_start_at' =>
                        $data->scheduledStartAt,
                ]);

                $batch->forceFill([
                    'status' =>
                        ProductionBatchStatus
                            ::Planned,

                    'created_by' =>
                        $actor->getKey(),

                    'updated_by' =>
                        $actor->getKey(),

                    'lock_version' => 1,

                    'source_system' => 'manual',

                    'import_status' =>
                        ProductionImportStatus
                            ::NotApplicable,
                ])->save();

                $this->audit(
                    action:
                        AuditAction
                            ::ProductionBatchCreated,

                    actor: $actor,
                    auditable: $batch,

                    newValues: [
                        'batch_number' =>
                            $batch->batch_number,

                        'production_order_id' =>
                            $batch
                                ->production_order_id,

                        'sequence_number' =>
                            $batch->sequence_number,

                        'status' =>
                            $batch->status->value,

                        'planned_quantity' =>
                            $batch
                                ->planned_quantity,
                    ]
                );

                return $batch->refresh();
            },
            attempts: 3
        );
    }

    /**
     * Change a batch lifecycle state.
     */
    public function transitionBatch(
        User $actor,
        int $batchId,
        ProductionBatchStatus $target,
        int $expectedVersion
    ): ProductionBatch {
        $this->authorizer
            ->assertCanManageBatches($actor);

        return DB::transaction(
            function () use (
                $actor,
                $batchId,
                $target,
                $expectedVersion
            ): ProductionBatch {
                $batch = ProductionBatch::query()
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                $order = ProductionOrder::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $batch->production_order_id
                    );

                $this->assertExpectedVersion(
                    $batch,
                    $expectedVersion
                );

                $batch->assertCanTransitionTo(
                    $target
                );

                $changes = [
                    'status' => $target,
                    'updated_by' =>
                        $actor->getKey(),
                ];

                if (
                    $target
                    === ProductionBatchStatus::Ready
                    && ! in_array(
                        $order->status,
                        [
                            ProductionOrderStatus
                                ::Released,

                            ProductionOrderStatus
                                ::InProgress,
                        ],
                        true
                    )
                ) {
                    throw new ProductionWorkflowException(
                        'A batch can become ready only when its order is released or in progress.'
                    );
                }

                if (
                    $target
                    === ProductionBatchStatus
                        ::InProgress
                ) {
                    if (
                        ! in_array(
                            $order->status,
                            [
                                ProductionOrderStatus
                                    ::Released,

                                ProductionOrderStatus
                                    ::InProgress,
                            ],
                            true
                        )
                    ) {
                        throw new ProductionWorkflowException(
                            'The production order is not available for batch execution.'
                        );
                    }

                    $changes['actual_start_at'] =
                        $batch->actual_start_at
                        ?? now();

                    if (
                        $order->status
                        === ProductionOrderStatus
                            ::Released
                    ) {
                        $this->moveOrderToInProgress(
                            $actor,
                            $order
                        );
                    }
                }

                if (
                    $target
                    === ProductionBatchStatus
                        ::Completed
                ) {
                    $aggregates =
                        $this
                            ->validatedRecordAggregates(
                                $batch
                            );

                    $changes[
                        'actual_good_quantity'
                    ] = $aggregates['good'];

                    $changes[
                        'actual_rejected_quantity'
                    ] = $aggregates['rejected'];

                    $changes['actual_end_at'] =
                        $batch->actual_end_at
                        ?? now();
                }

                if (
                    $target
                    === ProductionBatchStatus
                        ::Cancelled
                    && $batch->records()
                        ->exists()
                ) {
                    throw new ProductionWorkflowException(
                        'A batch containing production records cannot be cancelled.'
                    );
                }

                $previousStatus =
                    $batch->status;

                $updated =
                    $this->repository->updateBatch(
                        batch: $batch,
                        changes: $changes,
                        expectedVersion:
                            $expectedVersion
                    );

                $this->auditStatusChange(
                    AuditAction
                        ::ProductionBatchStatusChanged,
                    $actor,
                    $updated,
                    $previousStatus->value,
                    $updated->status->value
                );

                if ($target->isTerminal()) {
                    $this
                        ->synchronizeOrderTerminalState(
                            $actor,
                            $order
                        );
                }

                return $updated;
            },
            attempts: 3
        );
    }

    /**
     * Create a draft production record.
     */
    public function createRecord(
        User $actor,
        CreateProductionRecordData $data
    ): ProductionRecord {
        $this->authorizer
            ->assertCanCreateRecord($actor);

        return DB::transaction(
            function () use (
                $actor,
                $data
            ): ProductionRecord {
                $batch = ProductionBatch::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $data->productionBatchId
                    );

                if (
                    $batch->status
                    !== ProductionBatchStatus
                        ::InProgress
                ) {
                    throw new ProductionWorkflowException(
                        'Production records may be created only for an in-progress batch.'
                    );
                }

                $order = ProductionOrder::query()
                    ->findOrFail(
                        $batch->production_order_id
                    );

                $line = ProductionLine::query()
                    ->findOrFail(
                        $order->production_line_id
                    );

                $shift = Shift::query()
                    ->findOrFail($data->shiftId);

                $operator = Operator::query()
                    ->findOrFail(
                        $data->operatorId
                    );

                $this->assertActive(
                    $line,
                    'production line'
                );

                $this->assertActive(
                    $shift,
                    'shift'
                );

                $this->assertActive(
                    $operator,
                    'operator'
                );

                if (
                    $order->shift_id !== null
                    && $order->shift_id
                        !== $shift->getKey()
                ) {
                    throw new ProductionWorkflowException(
                        'The production record shift does not match the production order.'
                    );
                }

                if (
                    $this->authorizer
                        ->isOperator($actor)
                ) {
                    if (
                        $operator->user_id
                        !== $actor->getKey()
                    ) {
                        throw new \Illuminate\Auth\Access\AuthorizationException(
                            'Operators may create records only for their own employee account.'
                        );
                    }

                    $this->assertOperatorAssignment(
                        operator: $operator,
                        line: $line,
                        shift: $shift,
                        date: $data->productionDate
                    );
                }

                $this->validateRecordValues(
                    $data
                );

                $record = new ProductionRecord();

                $record->fill([
                    'record_number' =>
                        $this->generateNumber('PR'),

                    'production_batch_id' =>
                        $batch->getKey(),

                    'production_line_id' =>
                        $line->getKey(),

                    'shift_id' =>
                        $shift->getKey(),

                    'operator_id' =>
                        $operator->getKey(),

                    'production_date' =>
                        $data->productionDate,

                    'started_at' =>
                        $data->startedAt,

                    'ended_at' =>
                        $data->endedAt,

                    'produced_quantity' =>
                        $data->producedQuantity,

                    'good_quantity' =>
                        $data->goodQuantity,

                    'rejected_quantity' =>
                        $data->rejectedQuantity,

                    'quantity_unit' =>
                        $this->requiredText(
                            $data->quantityUnit,
                            'Quantity unit',
                            30
                        ),

                    'runtime_minutes' =>
                        $data->runtimeMinutes,

                    'downtime_minutes' =>
                        $data->downtimeMinutes,

                    'notes' =>
                        $this->nullableText(
                            $data->notes,
                            5000
                        ),
                ]);

                $record->forceFill([
                    'recorded_by' =>
                        $actor->getKey(),

                    'updated_by' =>
                        $actor->getKey(),

                    'status' =>
                        ProductionRecordStatus
                            ::Draft,

                    'validation_status' =>
                        ProductionValidationStatus
                            ::Pending,

                    'lock_version' => 1,

                    'source_system' => 'manual',

                    'import_status' =>
                        ProductionImportStatus
                            ::NotApplicable,
                ])->save();

                $this->audit(
                    action:
                        AuditAction
                            ::ProductionRecordCreated,

                    actor: $actor,
                    auditable: $record,

                    newValues: [
                        'record_number' =>
                            $record->record_number,

                        'production_batch_id' =>
                            $record
                                ->production_batch_id,

                        'production_line_id' =>
                            $record
                                ->production_line_id,

                        'shift_id' =>
                            $record->shift_id,

                        'operator_id' =>
                            $record->operator_id,

                        'status' =>
                            $record->status->value,

                        'produced_quantity' =>
                            $record
                                ->produced_quantity,

                        'good_quantity' =>
                            $record->good_quantity,

                        'rejected_quantity' =>
                            $record
                                ->rejected_quantity,
                    ]
                );

                return $record->refresh();
            },
            attempts: 3
        );
    }

    /**
     * Submit a draft record to the supervisor validation queue.
     */
    public function submitRecord(
        User $actor,
        int $recordId,
        int $expectedVersion
    ): ProductionRecord {
        return DB::transaction(
            function () use (
                $actor,
                $recordId,
                $expectedVersion
            ): ProductionRecord {
                $record = ProductionRecord::query()
                    ->with([
                        'operator.user',
                        'productionBatch',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($recordId);

                $this->authorizer
                    ->assertCanSubmitRecord(
                        $actor,
                        $record
                    );

                $this->assertExpectedVersion(
                    $record,
                    $expectedVersion
                );

                if (
                    ! in_array(
                        $record
                            ->productionBatch
                            ->status,
                        [
                            ProductionBatchStatus
                                ::InProgress,

                            ProductionBatchStatus
                                ::Completed,
                        ],
                        true
                    )
                ) {
                    throw new ProductionWorkflowException(
                        'The production batch does not accept record submissions.'
                    );
                }

                if (! $record->canBeSubmitted()) {
                    throw new ProductionWorkflowException(
                        'The production record is incomplete or contains inconsistent quantities.'
                    );
                }

                $changes = [
                    'status' =>
                        ProductionRecordStatus
                            ::Submitted,

                    'submitted_at' => now(),

                    'updated_by' =>
                        $actor->getKey(),
                ];

                if (
                    $record->validation_status
                    === ProductionValidationStatus
                        ::Rejected
                ) {
                    $changes[
                        'validation_status'
                    ] =
                        ProductionValidationStatus
                            ::Pending;
                }

                $updated =
                    $this->repository->updateRecord(
                        record: $record,
                        changes: $changes,
                        expectedVersion:
                            $expectedVersion
                    );

                $this->audit(
                    action:
                        AuditAction
                            ::ProductionRecordSubmitted,

                    actor: $actor,
                    auditable: $updated,

                    oldValues: [
                        'status' =>
                            ProductionRecordStatus
                                ::Draft
                                ->value,
                    ],

                    newValues: [
                        'status' =>
                            $updated->status->value,

                        'validation_status' =>
                            $updated
                                ->validation_status
                                ->value,

                        'submitted_at' =>
                            $updated
                                ->submitted_at
                                ?->toIso8601String(),
                    ]
                );

                return $updated;
            },
            attempts: 3
        );
    }

    /**
     * Validate or reject a submitted production record.
     */
    public function decideRecord(
        User $actor,
        int $recordId,
        ProductionValidationDecision $decision,
        int $expectedVersion,
        ?string $reason = null
    ): ProductionRecord {
        $this->authorizer
            ->assertCanDecideRecord($actor);

        return DB::transaction(
            function () use (
                $actor,
                $recordId,
                $decision,
                $expectedVersion,
                $reason
            ): ProductionRecord {
                $record = ProductionRecord::query()
                    ->lockForUpdate()
                    ->findOrFail($recordId);

                $this->assertExpectedVersion(
                    $record,
                    $expectedVersion
                );

                if (! $record->canBeValidated()) {
                    throw new ProductionWorkflowException(
                        'Only submitted records with a pending decision may be reviewed.'
                    );
                }

                $normalizedReason =
                    $this->nullableText(
                        $reason,
                        2000
                    );

                if (
                    $decision
                    === ProductionValidationDecision
                        ::Rejected
                    && $normalizedReason === null
                ) {
                    throw new ProductionWorkflowException(
                        'A rejection reason is required.'
                    );
                }

                $validated =
                    $decision
                    === ProductionValidationDecision
                        ::Validated;

                $updated =
                    $this->repository->updateRecord(
                        record: $record,

                        changes: [
                            'status' => $validated
                                ? ProductionRecordStatus
                                    ::Locked
                                : ProductionRecordStatus
                                    ::Draft,

                            'validation_status' =>
                                $decision
                                    ->validationStatus(),

                            'locked_at' => $validated
                                ? now()
                                : null,

                            'updated_by' =>
                                $actor->getKey(),
                        ],

                        expectedVersion:
                            $expectedVersion
                    );

                $validation =
                    new ProductionRecordValidation();

                $validation->forceFill([
                    'production_record_id' =>
                        $updated->getKey(),

                    'decided_by' =>
                        $actor->getKey(),

                    'decision' =>
                        $decision,

                    'record_version' =>
                        $expectedVersion,

                    'decision_reason' =>
                        $normalizedReason,

                    'decided_at' => now(),

                    'request_id' =>
                        $this->requestId(),
                ])->save();

                $this->audit(
                    action: $validated
                        ? AuditAction
                            ::ProductionRecordValidated
                        : AuditAction
                            ::ProductionRecordRejected,

                    actor: $actor,
                    auditable: $updated,

                    oldValues: [
                        'status' =>
                            ProductionRecordStatus
                                ::Submitted
                                ->value,

                        'validation_status' =>
                            ProductionValidationStatus
                                ::Pending
                                ->value,
                    ],

                    newValues: [
                        'status' =>
                            $updated->status->value,

                        'validation_status' =>
                            $updated
                                ->validation_status
                                ->value,

                        'reviewed_version' =>
                            $expectedVersion,

                        'decision_id' =>
                            $validation->getKey(),
                    ],

                    metadata: [
                        'reason_provided' =>
                            $normalizedReason !== null,
                    ]
                );

                return $updated;
            },
            attempts: 3
        );
    }

    /**
     * Report a production, downtime, incident, quality or comment event.
     */
    public function createEvent(
        User $actor,
        CreateProductionEventData $data
    ): ProductionEvent {
        $this->authorizer
            ->assertCanReportEvent(
                $actor,
                $data->eventType
            );

        return DB::transaction(
            function () use (
                $actor,
                $data
            ): ProductionEvent {
                $batch = ProductionBatch::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $data->productionBatchId
                    );

                $order = ProductionOrder::query()
                    ->findOrFail(
                        $batch->production_order_id
                    );

                $line = ProductionLine::query()
                    ->findOrFail(
                        $order->production_line_id
                    );

                $shiftId =
                    $data->shiftId
                    ?? $order->shift_id;

                $shift = $shiftId === null
                    ? null
                    : Shift::query()
                        ->findOrFail($shiftId);

                if ($shift !== null) {
                    $this->assertActive(
                        $shift,
                        'shift'
                    );
                }

                $operator = $data->operatorId === null
                    ? null
                    : Operator::query()
                        ->findOrFail(
                            $data->operatorId
                        );

                if ($operator !== null) {
                    $this->assertActive(
                        $operator,
                        'operator'
                    );
                }

                if (
                    $this->authorizer
                        ->isOperator($actor)
                ) {
                    $operator = Operator::query()
                        ->where(
                            'user_id',
                            $actor->getKey()
                        )
                        ->first();

                    if (
                        $operator === null
                        || $shift === null
                    ) {
                        throw new \Illuminate\Auth\Access\AuthorizationException(
                            'The operator account is not linked to a valid operational assignment.'
                        );
                    }

                    if (
                        $data->operatorId !== null
                        && $data->operatorId
                            !== $operator->getKey()
                    ) {
                        throw new \Illuminate\Auth\Access\AuthorizationException(
                            'Operators may report events only under their own employee identity.'
                        );
                    }

                    $this->assertOperatorAssignment(
                        operator: $operator,
                        line: $line,
                        shift: $shift,
                        date: $data->startedAt
                    );
                }

                $machine = $data->machineId === null
                    ? null
                    : Machine::query()
                        ->findOrFail(
                            $data->machineId
                        );

                if (
                    $data->eventType
                        ->requiresMachine()
                    && $machine === null
                ) {
                    throw new ProductionWorkflowException(
                        'A machine incident must reference a machine.'
                    );
                }

                if (
                    $machine !== null
                    && $machine->production_line_id
                        !== $line->getKey()
                ) {
                    throw new ProductionWorkflowException(
                        'The selected machine does not belong to the production line.'
                    );
                }

                if (
                    $data->productionRecordId
                    !== null
                ) {
                    $record =
                        ProductionRecord::query()
                            ->findOrFail(
                                $data
                                    ->productionRecordId
                            );

                    if (
                        $record
                            ->production_batch_id
                        !== $batch->getKey()
                    ) {
                        throw new ProductionWorkflowException(
                            'The production event record does not belong to the selected batch.'
                        );
                    }
                }

                $title = $this->requiredText(
                    $data->title,
                    'Event title',
                    180
                );

                $description =
                    $this->nullableText(
                        $data->description,
                        5000
                    );

                $duration =
                    $this->timelineDuration(
                        $data->startedAt,
                        $data->endedAt
                    );

                $event = new ProductionEvent();

                $event->fill([
                    'event_number' =>
                        $this->generateNumber('EVT'),

                    'production_batch_id' =>
                        $batch->getKey(),

                    'production_record_id' =>
                        $data->productionRecordId,

                    'production_line_id' =>
                        $line->getKey(),

                    'machine_id' =>
                        $machine?->getKey(),

                    'shift_id' =>
                        $shift?->getKey(),

                    'operator_id' =>
                        $operator?->getKey(),

                    'event_type' =>
                        $data->eventType,

                    'severity' =>
                        $data->severity,

                    'title' => $title,

                    'description' =>
                        $description,

                    'started_at' =>
                        $data->startedAt,

                    'ended_at' =>
                        $data->endedAt,

                    'duration_minutes' =>
                        $duration,
                ]);

                $event->forceFill([
                    'reported_by' =>
                        $actor->getKey(),

                    'is_resolved' => false,

                    'resolved_at' => null,

                    'resolved_by' => null,

                    'lock_version' => 1,

                    'source_system' => 'manual',

                    'import_status' =>
                        ProductionImportStatus
                            ::NotApplicable,
                ])->save();

                $this->audit(
                    action:
                        AuditAction
                            ::ProductionEventReported,

                    actor: $actor,
                    auditable: $event,

                    newValues: [
                        'event_number' =>
                            $event->event_number,

                        'event_type' =>
                            $event
                                ->event_type
                                ->value,

                        'severity' =>
                            $event->severity->value,

                        'production_batch_id' =>
                            $event
                                ->production_batch_id,

                        'production_line_id' =>
                            $event
                                ->production_line_id,

                        'machine_id' =>
                            $event->machine_id,

                        'is_resolved' => false,
                    ]
                );

                return $event->refresh();
            },
            attempts: 3
        );
    }

    /**
     * Resolve an open production event.
     */
    public function resolveEvent(
        User $actor,
        int $eventId,
        int $expectedVersion
    ): ProductionEvent {
        $this->authorizer
            ->assertCanResolveEvent($actor);

        return DB::transaction(
            function () use (
                $actor,
                $eventId,
                $expectedVersion
            ): ProductionEvent {
                $event = ProductionEvent::query()
                    ->lockForUpdate()
                    ->findOrFail($eventId);

                $this->assertExpectedVersion(
                    $event,
                    $expectedVersion
                );

                if (! $event->canBeResolved()) {
                    throw new ProductionWorkflowException(
                        'The production event is already resolved.'
                    );
                }

                $endedAt = $event->ended_at
                    ?? CarbonImmutable::now();

                $duration =
                    $this->timelineDuration(
                        CarbonImmutable::instance(
                            $event->started_at
                        ),
                        CarbonImmutable::instance(
                            $endedAt
                        )
                    );

                $updated =
                    $this->repository->updateEvent(
                        event: $event,

                        changes: [
                            'ended_at' =>
                                $endedAt,

                            'duration_minutes' =>
                                $duration,

                            'is_resolved' => true,

                            'resolved_at' => now(),

                            'resolved_by' =>
                                $actor->getKey(),
                        ],

                        expectedVersion:
                            $expectedVersion
                    );

                $this->audit(
                    action:
                        AuditAction
                            ::ProductionEventResolved,

                    actor: $actor,
                    auditable: $updated,

                    oldValues: [
                        'is_resolved' => false,
                    ],

                    newValues: [
                        'is_resolved' => true,

                        'resolved_by' =>
                            $actor->getKey(),

                        'resolved_at' =>
                            $updated
                                ->resolved_at
                                ?->toIso8601String(),

                        'duration_minutes' =>
                            $updated
                                ->duration_minutes,
                    ]
                );

                return $updated;
            },
            attempts: 3
        );
    }

    private function validateOrderTransition(
        ProductionOrder $order,
        ProductionOrderStatus $target
    ): void {
        $order->assertCanTransitionTo(
            $target
        );

        if (
            $target
            === ProductionOrderStatus::Released
        ) {
            $this->assertActive(
                $order->product,
                'product'
            );

            $this->assertActive(
                $order->productionLine,
                'production line'
            );

            if (
                ! $order->hasValidPlannedWindow()
                || ! $order
                    ->hasPositiveTargetQuantity()
            ) {
                throw new ProductionWorkflowException(
                    'The production order has an invalid planning window or target.'
                );
            }
        }

        if (
            $target
            === ProductionOrderStatus
                ::InProgress
            && ! $order->batches()
                ->whereIn(
                    'status',
                    [
                        ProductionBatchStatus
                            ::Ready
                            ->value,

                        ProductionBatchStatus
                            ::InProgress
                            ->value,
                    ]
                )
                ->exists()
        ) {
            throw new ProductionWorkflowException(
                'At least one ready batch is required to start the production order.'
            );
        }

        if (
            $target
            === ProductionOrderStatus
                ::Completed
        ) {
            $batches = $order->batches;

            if ($batches->isEmpty()) {
                throw new ProductionWorkflowException(
                    'A production order without batches cannot be completed.'
                );
            }

            $allTerminal = $batches->every(
                fn (ProductionBatch $batch): bool =>
                    $batch->status->isTerminal()
            );

            $hasCompleted = $batches->contains(
                fn (ProductionBatch $batch): bool =>
                    $batch->status
                    === ProductionBatchStatus
                        ::Completed
            );

            if (
                ! $allTerminal
                || ! $hasCompleted
            ) {
                throw new ProductionWorkflowException(
                    'All batches must be terminal and at least one batch must be completed.'
                );
            }
        }

        if (
            $target
            === ProductionOrderStatus
                ::Cancelled
            && $order->batches()
                ->where(
                    'status',
                    ProductionBatchStatus
                        ::InProgress
                        ->value
                )
                ->exists()
        ) {
            throw new ProductionWorkflowException(
                'An order with an in-progress batch cannot be cancelled.'
            );
        }
    }

    private function moveOrderToInProgress(
        User $actor,
        ProductionOrder $order
    ): void {
        $previous =
            $order->status;

        $order->assertCanTransitionTo(
            ProductionOrderStatus
                ::InProgress
        );

        $updated =
            $this->repository->updateOrder(
                order: $order,

                changes: [
                    'status' =>
                        ProductionOrderStatus
                            ::InProgress,

                    'updated_by' =>
                        $actor->getKey(),
                ],

                expectedVersion:
                    $order->lock_version
            );

        $this->auditStatusChange(
            AuditAction
                ::ProductionOrderStatusChanged,
            $actor,
            $updated,
            $previous->value,
            $updated->status->value
        );
    }

    private function synchronizeOrderTerminalState(
        User $actor,
        ProductionOrder $order
    ): void {
        $order->refresh();

        if ($order->status->isTerminal()) {
            return;
        }

        $batches = ProductionBatch::query()
            ->where(
                'production_order_id',
                $order->getKey()
            )
            ->lockForUpdate()
            ->get();

        if (
            $batches->isEmpty()
            || ! $batches->every(
                fn (ProductionBatch $batch): bool =>
                    $batch->status->isTerminal()
            )
        ) {
            return;
        }

        $allCancelled = $batches->every(
            fn (ProductionBatch $batch): bool =>
                $batch->status
                === ProductionBatchStatus
                    ::Cancelled
        );

        $target = $allCancelled
            ? ProductionOrderStatus::Cancelled
            : ProductionOrderStatus::Completed;

        if (! $order->canTransitionTo($target)) {
            return;
        }

        $previous =
            $order->status;

        $updated =
            $this->repository->updateOrder(
                order: $order,

                changes: [
                    'status' => $target,

                    'updated_by' =>
                        $actor->getKey(),
                ],

                expectedVersion:
                    $order->lock_version
            );

        $this->auditStatusChange(
            AuditAction
                ::ProductionOrderStatusChanged,
            $actor,
            $updated,
            $previous->value,
            $updated->status->value
        );
    }

    /**
     * @return array{good: string, rejected: string}
     */
    private function validatedRecordAggregates(
        ProductionBatch $batch
    ): array {
        $records = ProductionRecord::query()
            ->where(
                'production_batch_id',
                $batch->getKey()
            )
            ->lockForUpdate()
            ->get();

        if ($records->isEmpty()) {
            throw new ProductionWorkflowException(
                'A batch requires at least one validated production record before completion.'
            );
        }

        foreach ($records as $record) {
            if (
                $record->status
                    !== ProductionRecordStatus
                        ::Locked
                || $record->validation_status
                    !== ProductionValidationStatus
                        ::Validated
            ) {
                throw new ProductionWorkflowException(
                    'Every production record must be validated and locked before batch completion.'
                );
            }
        }

        $goodMilli = $records->sum(
            fn (ProductionRecord $record): int =>
                $this->quantityToMilliUnits(
                    $record->good_quantity
                )
        );

        $rejectedMilli = $records->sum(
            fn (ProductionRecord $record): int =>
                $this->quantityToMilliUnits(
                    $record->rejected_quantity
                )
        );

        if (
            $goodMilli + $rejectedMilli
            <= 0
        ) {
            throw new ProductionWorkflowException(
                'A completed batch must contain a positive production quantity.'
            );
        }

        return [
            'good' =>
                $this->milliUnitsToQuantity(
                    $goodMilli
                ),

            'rejected' =>
                $this->milliUnitsToQuantity(
                    $rejectedMilli
                ),
        ];
    }

    private function validateRecordValues(
        CreateProductionRecordData $data
    ): void {
        $produced =
            $this->quantityToMilliUnits(
                $data->producedQuantity
            );

        $good =
            $this->quantityToMilliUnits(
                $data->goodQuantity
            );

        $rejected =
            $this->quantityToMilliUnits(
                $data->rejectedQuantity
            );

        if (
            $produced < 0
            || $good < 0
            || $rejected < 0
        ) {
            throw new ProductionWorkflowException(
                'Production quantities cannot be negative.'
            );
        }

        if ($produced !== $good + $rejected) {
            throw new ProductionWorkflowException(
                'Produced quantity must equal good quantity plus rejected quantity.'
            );
        }

        if (
            $data->runtimeMinutes < 0
            || $data->downtimeMinutes < 0
        ) {
            throw new ProductionWorkflowException(
                'Runtime and downtime cannot be negative.'
            );
        }

        if (
            $data->startedAt !== null
            && $data->endedAt !== null
        ) {
            $elapsed =
                $this->timelineDuration(
                    $data->startedAt,
                    $data->endedAt
                );

            if (
                $data->runtimeMinutes
                + $data->downtimeMinutes
                > $elapsed
            ) {
                throw new ProductionWorkflowException(
                    'Runtime plus downtime cannot exceed the recorded timeline.'
                );
            }
        }
    }

    private function assertOperatorAssignment(
        Operator $operator,
        ProductionLine $line,
        Shift $shift,
        DateTimeInterface $date
    ): void {
        $assigned =
            OperatorAssignment::query()
                ->current($date)
                ->where(
                    'operator_id',
                    $operator->getKey()
                )
                ->where(
                    'production_line_id',
                    $line->getKey()
                )
                ->where(
                    'shift_id',
                    $shift->getKey()
                )
                ->exists();

        if (! $assigned) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'The operator is not assigned to this production line and shift.'
            );
        }
    }

    private function assertExpectedVersion(
        Model $model,
        int $expectedVersion
    ): void {
        if (
            (int) $model->getAttribute(
                'lock_version'
            ) !== $expectedVersion
        ) {
            throw OptimisticLockException::stale(
                $model,
                $expectedVersion
            );
        }
    }

    private function assertActive(
        Model $model,
        string $label
    ): void {
        if (
            ! (bool) $model->getAttribute(
                'is_active'
            )
        ) {
            throw new ProductionWorkflowException(
                sprintf(
                    'The selected %s is inactive.',
                    $label
                )
            );
        }
    }

    private function timelineDuration(
        CarbonImmutable $startedAt,
        ?CarbonImmutable $endedAt
    ): ?int {
        if ($endedAt === null) {
            return null;
        }

        if (
            $endedAt->lessThan($startedAt)
        ) {
            throw new ProductionWorkflowException(
                'The end timestamp cannot be earlier than the start timestamp.'
            );
        }

        return (int) round(
            $startedAt->diffInMinutes(
                $endedAt
            )
        );
    }

    private function quantityToMilliUnits(
        string|int|float|null $quantity
    ): int {
        return (int) round(
            ((float) $quantity) * 1000
        );
    }

    private function milliUnitsToQuantity(
        int $quantity
    ): string {
        return number_format(
            $quantity / 1000,
            3,
            '.',
            ''
        );
    }

    private function generateNumber(
        string $prefix
    ): string {
        return $prefix
            .'-MAN-'
            .Str::upper(
                (string) Str::ulid()
            );
    }

    private function requiredText(
        string $value,
        string $label,
        int $maximumLength
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new ProductionWorkflowException(
                $label.' is required.'
            );
        }

        if (
            mb_strlen($value)
            > $maximumLength
        ) {
            throw new ProductionWorkflowException(
                sprintf(
                    '%s may not exceed %d characters.',
                    $label,
                    $maximumLength
                )
            );
        }

        return $value;
    }

    private function nullableText(
        ?string $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > $maximumLength
        ) {
            throw new ProductionWorkflowException(
                sprintf(
                    'Text may not exceed %d characters.',
                    $maximumLength
                )
            );
        }

        return $value;
    }

    private function auditStatusChange(
        AuditAction $action,
        User $actor,
        Model $auditable,
        string $previousStatus,
        string $newStatus
    ): void {
        $this->audit(
            action: $action,
            actor: $actor,
            auditable: $auditable,
            oldValues: [
                'status' => $previousStatus,
            ],
            newValues: [
                'status' => $newStatus,

                'lock_version' =>
                    $auditable->getAttribute(
                        'lock_version'
                    ),
            ]
        );
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $metadata
     */
    private function audit(
        AuditAction $action,
        User $actor,
        Model $auditable,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): void {
        $requestId = $this->requestId();

        if ($requestId !== null) {
            $metadata['request_id'] =
                $requestId;
        }

        $metadata['workflow'] =
            'production';

        $this->auditLogService->record(
            action: $action,
            actor: $actor,
            auditable: $auditable,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata
        );
    }

    private function requestId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        if (! $request instanceof Request) {
            return null;
        }

        $requestId =
            $request->attributes->get(
                'request_id'
            );

        return is_string($requestId)
            && $requestId !== ''
                ? $requestId
                : null;
    }
}