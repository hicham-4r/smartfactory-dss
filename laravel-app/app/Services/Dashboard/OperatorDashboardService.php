<?php

namespace App\Services\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
use App\DTOs\Dashboard\OperatorDashboardAssignmentItem;
use App\DTOs\Dashboard\OperatorDashboardEventItem;
use App\DTOs\Dashboard\OperatorDashboardOrderItem;
use App\DTOs\Dashboard\OperatorDashboardRecordItem;
use App\DTOs\Dashboard\OperatorDashboardSnapshot;
use App\DTOs\Dashboard\OperatorDashboardUnitSummary;
use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OperatorDashboardService
{
    public function build(
        User $user,
        DashboardFilter $filter
    ): OperatorDashboardSnapshot {
        $operator = Operator::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->first();

        if ($operator === null) {
            return $this->emptySnapshot(
                filter: $filter,
                profileLinked: false,
                operatorActive: false,
            );
        }

        if (! $operator->is_active) {
            return $this->emptySnapshot(
                filter: $filter,
                profileLinked: true,
                operatorActive: false,
                operatorId: $operator->getKey(),
                employeeCode: $operator->employee_code,
                operatorName: $operator->full_name,
            );
        }

        $referenceDate = CarbonImmutable::now(
            $filter->timezone
        )->startOfDay();

        $assignments = OperatorAssignment::query()
            ->current($referenceDate)
            ->where(
                'operator_id',
                $operator->getKey()
            )
            ->with([
                'productionLine',
                'shift',
            ])
            ->orderByDesc('is_primary')
            ->orderBy('production_line_id')
            ->orderBy('shift_id')
            ->get();

        $recordQuery = ProductionRecord::query()
            ->where(
                'operator_id',
                $operator->getKey()
            )
            ->whereDate(
                'production_date',
                '>=',
                $filter->startDateString()
            )
            ->whereDate(
                'production_date',
                '<=',
                $filter->endDateString()
            );

        $eventQuery = ProductionEvent::query()
            ->where(
                function (Builder $query) use (
                    $user,
                    $operator
                ): void {
                    $query
                        ->where(
                            'reported_by',
                            $user->getKey()
                        )
                        ->orWhere(
                            'operator_id',
                            $operator->getKey()
                        );
                }
            )
            ->whereIn(
                'event_type',
                [
                    ProductionEventType::Downtime->value,
                    ProductionEventType::MachineIncident->value,
                ]
            )
            ->where(
                'started_at',
                '>=',
                $filter->utcStart()
            )
            ->where(
                'started_at',
                '<',
                $filter->utcEndExclusive()
            );

        return new OperatorDashboardSnapshot(
            filter: $filter,
            profileLinked: true,
            operatorActive: true,
            operatorId: $operator->getKey(),
            employeeCode: $operator->employee_code,
            operatorName: $operator->full_name,

            assignments: $this->assignmentItems(
                $assignments
            ),

            assignedOrders: $this->assignedOrderItems(
                assignments: $assignments,
                timezone: $filter->timezone,
            ),

            recentRecords: $this->recentRecordItems(
                clone $recordQuery
            ),

            recentEvents: $this->recentEventItems(
                query: clone $eventQuery,
                timezone: $filter->timezone,
            ),

            quantityUnits: $this->quantityUnitSummaries(
                clone $recordQuery
            ),

            recordCount:
                (clone $recordQuery)->count(),

            draftRecordCount:
                (clone $recordQuery)
                    ->where(
                        'status',
                        ProductionRecordStatus::Draft->value
                    )
                    ->count(),

            submittedRecordCount:
                (clone $recordQuery)
                    ->where(
                        'status',
                        ProductionRecordStatus::Submitted->value
                    )
                    ->count(),

            pendingValidationCount:
                (clone $recordQuery)
                    ->where(
                        'status',
                        ProductionRecordStatus::Submitted->value
                    )
                    ->where(
                        'validation_status',
                        ProductionValidationStatus::Pending->value
                    )
                    ->count(),

            validatedRecordCount:
                (clone $recordQuery)
                    ->where(
                        'validation_status',
                        ProductionValidationStatus::Validated->value
                    )
                    ->count(),

            rejectedRecordCount:
                (clone $recordQuery)
                    ->where(
                        'validation_status',
                        ProductionValidationStatus::Rejected->value
                    )
                    ->count(),

            runtimeMinutes: (int) (
                (clone $recordQuery)
                    ->sum('runtime_minutes')
            ),

            downtimeMinutes: (int) (
                (clone $recordQuery)
                    ->sum('downtime_minutes')
            ),

            reportedDowntimeCount:
                (clone $eventQuery)
                    ->where(
                        'event_type',
                        ProductionEventType::Downtime->value
                    )
                    ->count(),

            reportedMachineIncidentCount:
                (clone $eventQuery)
                    ->where(
                        'event_type',
                        ProductionEventType::MachineIncident->value
                    )
                    ->count(),

            unresolvedEventCount:
                (clone $eventQuery)
                    ->where(
                        'is_resolved',
                        false
                    )
                    ->count(),
        );
    }

    /**
     * @param Collection<int, OperatorAssignment> $assignments
     * @return list<OperatorDashboardAssignmentItem>
     */
    private function assignmentItems(
        Collection $assignments
    ): array {
        return $assignments
            ->map(
                static fn (
                    OperatorAssignment $assignment
                ): OperatorDashboardAssignmentItem =>
                    new OperatorDashboardAssignmentItem(
                        id:
                            $assignment->getKey(),

                        productionLineId:
                            $assignment->production_line_id,

                        productionLineCode:
                            $assignment
                                ->productionLine
                                ->code,

                        productionLineName:
                            $assignment
                                ->productionLine
                                ->name,

                        shiftId:
                            $assignment->shift_id,

                        shiftCode:
                            $assignment
                                ->shift
                                ->code,

                        shiftName:
                            $assignment
                                ->shift
                                ->name,

                        startsOn:
                            $assignment
                                ->starts_on
                                ->toDateString(),

                        endsOn:
                            $assignment
                                ->ends_on
                                ?->toDateString(),

                        isPrimary:
                            (bool) $assignment
                                ->is_primary,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, OperatorAssignment> $assignments
     * @return list<OperatorDashboardOrderItem>
     */
    private function assignedOrderItems(
        Collection $assignments,
        string $timezone
    ): array {
        if ($assignments->isEmpty()) {
            return [];
        }

        $query = ProductionOrder::query()
            ->whereIn(
                'status',
                [
                    ProductionOrderStatus::Released->value,
                    ProductionOrderStatus::InProgress->value,
                ]
            )
            ->with([
                'product',
                'productionLine',
                'shift',

                /*
                 * Laravel passes a HasMany relation to this callback,
                 * not an Eloquent Builder instance.
                 */
                'batches' => fn ($query) =>
                    $query
                        ->where(
                            'status',
                            ProductionBatchStatus::InProgress->value
                        )
                        ->orderBy(
                            'sequence_number'
                        )
                        ->orderBy('id'),
            ]);

        $this->applyAssignmentScope(
            query: $query,
            assignments: $assignments,
        );

        return $query
            ->orderByRaw(
                "CASE status
                    WHEN 'in_progress' THEN 1
                    WHEN 'released' THEN 2
                    ELSE 3
                END"
            )
            ->orderBy('priority')
            ->orderBy('planned_start_at')
            ->limit(8)
            ->get()
            ->map(
                static function (
                    ProductionOrder $order
                ) use (
                    $timezone
                ): OperatorDashboardOrderItem {
                    $batch =
                        $order->batches->first();

                    return new OperatorDashboardOrderItem(
                        id:
                            $order->getKey(),

                        orderNumber:
                            $order->order_number,

                        productName:
                            $order
                                ->product
                                ->name,

                        productionLineName:
                            $order
                                ->productionLine
                                ->name,

                        shiftName:
                            $order
                                ->shift
                                ?->name,

                        status:
                            $order
                                ->status
                                ->value,

                        plannedStartAt:
                            $order
                                ->planned_start_at
                                ->setTimezone(
                                    $timezone
                                )
                                ->format(
                                    'Y-m-d H:i'
                                ),

                        targetQuantity:
                            (string) $order
                                ->target_quantity,

                        quantityUnit:
                            $order
                                ->quantity_unit,

                        priority:
                            $order->priority,

                        actionBatchId:
                            $batch?->getKey(),

                        actionBatchNumber:
                            $batch
                                ?->batch_number,
                    );
                }
            )
            ->values()
            ->all();
    }

    /**
     * @return list<OperatorDashboardRecordItem>
     */
    private function recentRecordItems(
        Builder $query
    ): array {
        return $query
            ->with([
                'productionBatch.productionOrder.product',
                'productionLine',
                'shift',
            ])
            ->orderByDesc(
                'production_date'
            )
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(
                static fn (
                    ProductionRecord $record
                ): OperatorDashboardRecordItem =>
                    new OperatorDashboardRecordItem(
                        id:
                            $record->getKey(),

                        recordNumber:
                            $record
                                ->record_number,

                        productName:
                            $record
                                ->productionBatch
                                ->productionOrder
                                ->product
                                ->name,

                        productionLineName:
                            $record
                                ->productionLine
                                ->name,

                        shiftName:
                            $record
                                ->shift
                                ->name,

                        productionDate:
                            $record
                                ->production_date
                                ->toDateString(),

                        producedQuantity:
                            (string) $record
                                ->produced_quantity,

                        goodQuantity:
                            (string) $record
                                ->good_quantity,

                        rejectedQuantity:
                            (string) $record
                                ->rejected_quantity,

                        quantityUnit:
                            $record
                                ->quantity_unit,

                        runtimeMinutes:
                            $record
                                ->runtime_minutes,

                        downtimeMinutes:
                            $record
                                ->downtime_minutes,

                        status:
                            $record
                                ->status
                                ->value,

                        validationStatus:
                            $record
                                ->validation_status
                                ->value,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @return list<OperatorDashboardEventItem>
     */
    private function recentEventItems(
        Builder $query,
        string $timezone
    ): array {
        return $query
            ->with([
                'productionLine',
                'machine',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(
                static fn (
                    ProductionEvent $event
                ): OperatorDashboardEventItem =>
                    new OperatorDashboardEventItem(
                        id:
                            $event->getKey(),

                        eventNumber:
                            $event
                                ->event_number,

                        title:
                            $event->title,

                        eventType:
                            $event
                                ->event_type
                                ->value,

                        severity:
                            $event
                                ->severity
                                ->value,

                        productionLineName:
                            $event
                                ->productionLine
                                ->name,

                        machineName:
                            $event
                                ->machine
                                ?->name,

                        startedAt:
                            $event
                                ->started_at
                                ->setTimezone(
                                    $timezone
                                )
                                ->format(
                                    'Y-m-d H:i'
                                ),

                        durationMinutes:
                            $event
                                ->duration_minutes,

                        isResolved:
                            (bool) $event
                                ->is_resolved,
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @return list<OperatorDashboardUnitSummary>
     */
    private function quantityUnitSummaries(
        Builder $query
    ): array {
        return $query
            ->selectRaw(
                'quantity_unit, '
                .'COUNT(*) AS record_count, '
                .'SUM(produced_quantity) AS produced_quantity, '
                .'SUM(good_quantity) AS good_quantity, '
                .'SUM(rejected_quantity) AS rejected_quantity'
            )
            ->groupBy('quantity_unit')
            ->orderBy('quantity_unit')
            ->get()
            ->map(
                fn (
                    ProductionRecord $record
                ): OperatorDashboardUnitSummary =>
                    new OperatorDashboardUnitSummary(
                        quantityUnit:
                            $record
                                ->quantity_unit,

                        recordCount:
                            (int) $record
                                ->record_count,

                        producedQuantity:
                            $this->decimalString(
                                $record
                                    ->produced_quantity
                            ),

                        goodQuantity:
                            $this->decimalString(
                                $record
                                    ->good_quantity
                            ),

                        rejectedQuantity:
                            $this->decimalString(
                                $record
                                    ->rejected_quantity
                            ),
                    )
            )
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, OperatorAssignment> $assignments
     */
    private function applyAssignmentScope(
        Builder $query,
        Collection $assignments
    ): void {
        $query->where(
            function (
                Builder $outer
            ) use (
                $assignments
            ): void {
                foreach (
                    $assignments as $assignment
                ) {
                    $outer->orWhere(
                        function (
                            Builder $scope
                        ) use (
                            $assignment
                        ): void {
                            $scope->where(
                                'production_line_id',
                                $assignment
                                    ->production_line_id
                            );

                            $scope->where(
                                function (
                                    Builder $shiftScope
                                ) use (
                                    $assignment
                                ): void {
                                    $shiftScope
                                        ->whereNull(
                                            'shift_id'
                                        )
                                        ->orWhere(
                                            'shift_id',
                                            $assignment
                                                ->shift_id
                                        );
                                }
                            );
                        }
                    );
                }
            }
        );
    }

    private function decimalString(
        mixed $value
    ): string {
        return number_format(
            (float) ($value ?? 0),
            3,
            '.',
            ''
        );
    }

    private function emptySnapshot(
        DashboardFilter $filter,
        bool $profileLinked,
        bool $operatorActive,
        ?int $operatorId = null,
        ?string $employeeCode = null,
        ?string $operatorName = null,
    ): OperatorDashboardSnapshot {
        return new OperatorDashboardSnapshot(
            filter: $filter,
            profileLinked: $profileLinked,
            operatorActive: $operatorActive,
            operatorId: $operatorId,
            employeeCode: $employeeCode,
            operatorName: $operatorName,
            assignments: [],
            assignedOrders: [],
            recentRecords: [],
            recentEvents: [],
            quantityUnits: [],
            recordCount: 0,
            draftRecordCount: 0,
            submittedRecordCount: 0,
            pendingValidationCount: 0,
            validatedRecordCount: 0,
            rejectedRecordCount: 0,
            runtimeMinutes: 0,
            downtimeMinutes: 0,
            reportedDowntimeCount: 0,
            reportedMachineIncidentCount: 0,
            unresolvedEventCount: 0,
        );
    }
}