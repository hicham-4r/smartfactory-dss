<?php

namespace App\Repositories\Eloquent;

use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Repositories\Contracts\ProductionExecutionRepositoryInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class EloquentProductionExecutionRepository implements
    ProductionExecutionRepositoryInterface
{
    /**
     * @var list<string>
     */
    private const ORDER_UPDATE_FIELDS = [
        'shift_id',
        'planned_start_at',
        'planned_end_at',
        'target_quantity',
        'quantity_unit',
        'priority',
        'instructions',
        'status',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    private const BATCH_UPDATE_FIELDS = [
        'planned_quantity',
        'actual_good_quantity',
        'actual_rejected_quantity',
        'quantity_unit',
        'scheduled_start_at',
        'actual_start_at',
        'actual_end_at',
        'status',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    private const RECORD_UPDATE_FIELDS = [
        'operator_id',
        'production_date',
        'started_at',
        'ended_at',
        'produced_quantity',
        'good_quantity',
        'rejected_quantity',
        'quantity_unit',
        'runtime_minutes',
        'downtime_minutes',
        'notes',
        'status',
        'validation_status',
        'submitted_at',
        'locked_at',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    private const EVENT_UPDATE_FIELDS = [
        'machine_id',
        'shift_id',
        'operator_id',
        'event_type',
        'severity',
        'title',
        'description',
        'started_at',
        'ended_at',
        'duration_minutes',
        'is_resolved',
        'resolved_at',
        'resolved_by',
    ];

    public function findOrder(
        int $orderId
    ): ProductionOrder {
        return ProductionOrder::query()
            ->with([
                'product.productFamily',
                'productionLine',
                'shift',
                'creator',
                'updater',
                'batches',
            ])
            ->findOrFail($orderId);
    }

    public function findBatch(
        int $batchId
    ): ProductionBatch {
        return ProductionBatch::query()
            ->with([
                'productionOrder.product',
                'productionOrder.productionLine',
                'creator',
                'updater',
                'records',
                'events',
            ])
            ->findOrFail($batchId);
    }

    public function findRecord(
        int $recordId
    ): ProductionRecord {
        return ProductionRecord::query()
            ->with([
                'productionBatch.productionOrder.product',
                'productionLine',
                'shift',
                'operator.user',
                'recordedBy',
                'updatedBy',
                'validations.decidedBy',
                'events',
            ])
            ->findOrFail($recordId);
    }

    public function findEvent(
        int $eventId
    ): ProductionEvent {
        return ProductionEvent::query()
            ->with([
                'productionBatch.productionOrder',
                'productionRecord',
                'productionLine',
                'machine',
                'shift',
                'operator',
                'reportedBy',
                'resolvedBy',
            ])
            ->findOrFail($eventId);
    }

    public function updateOrder(
        ProductionOrder $order,
        array $changes,
        int $expectedVersion
    ): ProductionOrder {
        $changes = $this->validatedChanges(
            $changes,
            self::ORDER_UPDATE_FIELDS
        );

        if (array_key_exists('status', $changes)) {
            $target = $this->orderStatus(
                $changes['status']
            );

            $order->assertCanTransitionTo(
                $target
            );

            $changes['status'] = $target;
        }

        return $order->updateWithOptimisticLock(
            $changes,
            $expectedVersion
        );
    }

    public function updateBatch(
        ProductionBatch $batch,
        array $changes,
        int $expectedVersion
    ): ProductionBatch {
        $changes = $this->validatedChanges(
            $changes,
            self::BATCH_UPDATE_FIELDS
        );

        if (array_key_exists('status', $changes)) {
            $target = $this->batchStatus(
                $changes['status']
            );

            $batch->assertCanTransitionTo(
                $target
            );

            $changes['status'] = $target;
        }

        return $batch->updateWithOptimisticLock(
            $changes,
            $expectedVersion
        );
    }

    public function updateRecord(
        ProductionRecord $record,
        array $changes,
        int $expectedVersion
    ): ProductionRecord {
        $changes = $this->validatedChanges(
            $changes,
            self::RECORD_UPDATE_FIELDS
        );

        if (array_key_exists('status', $changes)) {
            $target = $this->recordStatus(
                $changes['status']
            );

            $record->assertCanTransitionTo(
                $target
            );

            $changes['status'] = $target;
        }

        if (
            array_key_exists(
                'validation_status',
                $changes
            )
        ) {
            $target = $this->validationStatus(
                $changes['validation_status']
            );

            $record->assertCanTransitionValidationTo(
                $target
            );

            $changes['validation_status'] =
                $target;
        }

        return $record->updateWithOptimisticLock(
            $changes,
            $expectedVersion
        );
    }

    public function updateEvent(
        ProductionEvent $event,
        array $changes,
        int $expectedVersion
    ): ProductionEvent {
        $changes = $this->validatedChanges(
            $changes,
            self::EVENT_UPDATE_FIELDS
        );

        return $event->updateWithOptimisticLock(
            $changes,
            $expectedVersion
        );
    }

    public function pendingRecordsForValidation(
        int $limit = 50
    ): Collection {
        $limit = max(
            1,
            min($limit, 200)
        );

        return ProductionRecord::query()
            ->status(
                ProductionRecordStatus::Submitted
            )
            ->validationStatus(
                ProductionValidationStatus::Pending
            )
            ->with([
                'productionBatch.productionOrder.product',
                'productionLine',
                'shift',
                'operator',
                'recordedBy',
            ])
            ->orderBy('submitted_at')
            ->limit($limit)
            ->get();
    }

    public function unresolvedCriticalEvents(
        int $limit = 50
    ): Collection {
        $limit = max(
            1,
            min($limit, 200)
        );

        return ProductionEvent::query()
            ->unresolved()
            ->severity(
                ProductionEventSeverity::Critical
            )
            ->with([
                'productionLine',
                'machine',
                'productionBatch',
                'reportedBy',
            ])
            ->orderBy('started_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Reject unknown or security-sensitive update fields.
     *
     * @param array<string, mixed> $changes
     * @param list<string> $allowedFields
     *
     * @return array<string, mixed>
     */
    private function validatedChanges(
        array $changes,
        array $allowedFields
    ): array {
        $unknownFields = array_diff(
            array_keys($changes),
            $allowedFields
        );

        if ($unknownFields !== []) {
            throw new InvalidArgumentException(
                'Disallowed production update fields: '
                .implode(', ', $unknownFields)
            );
        }

        return $changes;
    }

    private function orderStatus(
        mixed $status
    ): ProductionOrderStatus {
        return $status instanceof ProductionOrderStatus
            ? $status
            : ProductionOrderStatus::from(
                (string) $status
            );
    }

    private function batchStatus(
        mixed $status
    ): ProductionBatchStatus {
        return $status instanceof ProductionBatchStatus
            ? $status
            : ProductionBatchStatus::from(
                (string) $status
            );
    }

    private function recordStatus(
        mixed $status
    ): ProductionRecordStatus {
        return $status instanceof ProductionRecordStatus
            ? $status
            : ProductionRecordStatus::from(
                (string) $status
            );
    }

    private function validationStatus(
        mixed $status
    ): ProductionValidationStatus {
        return $status
            instanceof ProductionValidationStatus
                ? $status
                : ProductionValidationStatus::from(
                    (string) $status
                );
    }
}