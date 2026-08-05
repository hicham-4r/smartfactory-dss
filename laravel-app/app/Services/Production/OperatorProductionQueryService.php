<?php

namespace App\Services\Production;

use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OperatorProductionQueryService
{
    public function operatorForOrFail(
        User $user
    ): Operator {
        $operator = Operator::query()
            ->where('user_id', $user->getKey())
            ->first();

        if ($operator === null) {
            throw new AuthorizationException(
                'Your login account is not linked to an operator employee record.'
            );
        }

        if (! $operator->is_active) {
            throw new AuthorizationException(
                'Your operator employee record is inactive.'
            );
        }

        return $operator;
    }

    /**
     * @return Collection<int, OperatorAssignment>
     */
    public function currentAssignments(
        User $user,
        DateTimeInterface $date
    ): Collection {
        $operator = $this->operatorForOrFail($user);

        return OperatorAssignment::query()
            ->current($date)
            ->where('operator_id', $operator->getKey())
            ->with([
                'operator',
                'productionLine',
                'shift',
            ])
            ->orderBy('production_line_id')
            ->orderBy('shift_id')
            ->get();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function assignedOrders(
        User $user,
        DateTimeInterface $referenceDate,
        array $filters = [],
        int $perPage = 12
    ): LengthAwarePaginator {
        $assignments = $this->currentAssignments(
            $user,
            $referenceDate
        );

        $query = ProductionOrder::query()
            ->with([
                'product',
                'productionLine',
                'shift',

                /*
                 * Load only batches that may still require operator action.
                 * Record creation remains protected by the existing policy,
                 * while downtime and machine incidents can be reported for
                 * ready, in-progress, or blocked assigned work.
                 */
                'batches' => fn ($query) =>
                    $query
                        ->whereIn(
                            'status',
                            [
                                ProductionBatchStatus::Ready->value,
                                ProductionBatchStatus::InProgress->value,
                                ProductionBatchStatus::Blocked->value,
                            ]
                        )
                        ->orderByRaw(
                            "CASE status
                                WHEN 'in_progress' THEN 1
                                WHEN 'ready' THEN 2
                                WHEN 'blocked' THEN 3
                                ELSE 4
                            END"
                        )
                        ->orderBy('sequence_number')
                        ->orderBy('id'),
            ])
            ->withCount('batches');

        $this->applyAssignmentScope(
            $query,
            $assignments
        );

        $status = $filters['status'] ?? null;

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [
                ProductionOrderStatus::Planned->value,
                ProductionOrderStatus::Released->value,
                ProductionOrderStatus::InProgress->value,
            ]);
        }

        $search = $filters['search'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $query->where(
                'order_number',
                'like',
                $this->likePattern($search)
            );
        }

        return $query
            ->orderBy('planned_start_at')
            ->orderBy('id')
            ->paginate(
                perPage: max(1, min($perPage, 50)),
                pageName: 'orders_page'
            )
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function ownRecords(
        User $user,
        array $filters = [],
        int $perPage = 10
    ): LengthAwarePaginator {
        $operator = $this->operatorForOrFail($user);

        $query = ProductionRecord::query()
            ->where(
                'operator_id',
                $operator->getKey()
            )
            ->with([
                'productionBatch.productionOrder.product',
                'productionLine',
                'shift',
            ]);

        $status = $filters['record_status'] ?? null;

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $search = $filters['record_search'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $query->where(
                'record_number',
                'like',
                $this->likePattern($search)
            );
        }

        return $query
            ->orderByDesc('production_date')
            ->orderByDesc('id')
            ->paginate(
                perPage: max(1, min($perPage, 50)),
                pageName: 'records_page'
            )
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function ownEvents(
        User $user,
        array $filters = [],
        int $perPage = 10
    ): LengthAwarePaginator {
        $operator = $this->operatorForOrFail($user);

        $query = ProductionEvent::query()
            ->where(function (Builder $scope) use (
                $user,
                $operator
            ): void {
                $scope
                    ->where(
                        'reported_by',
                        $user->getKey()
                    )
                    ->orWhere(
                        'operator_id',
                        $operator->getKey()
                    );
            })
            ->with([
                'productionBatch.productionOrder',
                'productionLine',
                'machine',
            ]);

        $eventType = $filters['event_type'] ?? null;

        if (
            is_string($eventType)
            && $eventType !== ''
        ) {
            $query->where(
                'event_type',
                $eventType
            );
        }

        $search = $filters['event_search'] ?? null;

        if (is_string($search) && trim($search) !== '') {
            $pattern = $this->likePattern($search);

            $query->where(
                function (Builder $scope) use (
                    $pattern
                ): void {
                    $scope
                        ->where(
                            'event_number',
                            'like',
                            $pattern
                        )
                        ->orWhere(
                            'title',
                            'like',
                            $pattern
                        );
                }
            );
        }

        return $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: max(1, min($perPage, 50)),
                pageName: 'events_page'
            )
            ->withQueryString();
    }

    /**
     * @return Collection<int, OperatorAssignment>
     */
    public function eligibleAssignmentsForBatch(
        User $user,
        ProductionBatch $batch,
        DateTimeInterface $date
    ): Collection {
        $operator = $this->operatorForOrFail($user);

        $batch->loadMissing(
            'productionOrder'
        );

        $order = $batch->productionOrder;

        $query = OperatorAssignment::query()
            ->current($date)
            ->where(
                'operator_id',
                $operator->getKey()
            )
            ->where(
                'production_line_id',
                $order->production_line_id
            )
            ->with([
                'productionLine',
                'shift',
                'operator',
            ]);

        if ($order->shift_id !== null) {
            $query->where(
                'shift_id',
                $order->shift_id
            );
        }

        return $query
            ->orderBy('shift_id')
            ->get();
    }

    public function resolveAssignmentForBatch(
        User $user,
        ProductionBatch $batch,
        int $shiftId,
        DateTimeInterface $date
    ): OperatorAssignment {
        $assignment = $this
            ->eligibleAssignmentsForBatch(
                $user,
                $batch,
                $date
            )
            ->first(
                fn (
                    OperatorAssignment $candidate
                ): bool =>
                    $candidate->shift_id === $shiftId
            );

        if ($assignment === null) {
            throw new AuthorizationException(
                'You are not assigned to this production line and shift for the selected date.'
            );
        }

        return $assignment;
    }

    /**
     * @return Collection<int, ProductionRecord>
     */
    public function recordsForBatch(
        User $user,
        ProductionBatch $batch
    ): Collection {
        $operator = $this->operatorForOrFail($user);

        return ProductionRecord::query()
            ->where(
                'production_batch_id',
                $batch->getKey()
            )
            ->where(
                'operator_id',
                $operator->getKey()
            )
            ->with([
                'shift',
                'validations.decidedBy',
            ])
            ->orderByDesc('production_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, ProductionEvent>
     */
    public function eventsForBatch(
        User $user,
        ProductionBatch $batch
    ): Collection {
        $operator = $this->operatorForOrFail($user);

        return ProductionEvent::query()
            ->where(
                'production_batch_id',
                $batch->getKey()
            )
            ->where(function (Builder $scope) use (
                $user,
                $operator
            ): void {
                $scope
                    ->where(
                        'reported_by',
                        $user->getKey()
                    )
                    ->orWhere(
                        'operator_id',
                        $operator->getKey()
                    );
            })
            ->with('machine')
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * @return Collection<int, Machine>
     */
    public function machinesForBatch(
        ProductionBatch $batch
    ): Collection {
        $batch->loadMissing(
            'productionOrder'
        );

        return Machine::query()
            ->where(
                'production_line_id',
                $batch
                    ->productionOrder
                    ->production_line_id
            )
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ProductionRecord>
     */
    public function selectableRecordsForEvent(
        User $user,
        ProductionBatch $batch
    ): Collection {
        return $this
            ->recordsForBatch($user, $batch)
            ->filter(
                fn (ProductionRecord $record): bool =>
                    in_array(
                        $record->status,
                        [
                            ProductionRecordStatus::Draft,
                            ProductionRecordStatus::Submitted,
                            ProductionRecordStatus::Locked,
                        ],
                        true
                    )
            )
            ->values();
    }

    /**
     * @param Collection<int, OperatorAssignment> $assignments
     */
    private function applyAssignmentScope(
        Builder $query,
        Collection $assignments
    ): void {
        if ($assignments->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(
            function (Builder $outer) use (
                $assignments
            ): void {
                foreach ($assignments as $assignment) {
                    $outer->orWhere(
                        function (Builder $scope) use (
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
                                ) use ($assignment): void {
                                    $shiftScope
                                        ->whereNull('shift_id')
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

    private function likePattern(
        string $value
    ): string {
        return '%'
            .addcslashes(
                trim($value),
                '\\%_'
            )
            .'%';
    }
}
