<?php

namespace App\Services\Production;

use App\Enums\PermissionName;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\RoleName;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\User;
use Carbon\CarbonImmutable;

final class ProductionAccessResolver
{
    /**
     * Return the employee record linked to one login account.
     */
    public function operatorFor(
        User $user
    ): ?Operator {
        return Operator::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->first();
    }

    /**
     * Determine whether a user may see one production order.
     */
    public function canViewOrder(
        User $user,
        ProductionOrder $order
    ): bool {
        if (
            $user->can(
                PermissionName
                    ::ViewAllProductionOrders
                    ->value
            )
        ) {
            return true;
        }

        if (
            ! $user->can(
                PermissionName
                    ::ViewAssignedProductionOrders
                    ->value
            )
        ) {
            return false;
        }

        $operator = $this->operatorFor(
            $user
        );

        if ($operator === null) {
            return false;
        }

        $effectiveDate =
            $order->planned_start_at
            ?? CarbonImmutable::today();

        $query = OperatorAssignment::query()
            ->current($effectiveDate)
            ->where(
                'operator_id',
                $operator->getKey()
            )
            ->where(
                'production_line_id',
                $order->production_line_id
            );

        if ($order->shift_id !== null) {
            $query->where(
                'shift_id',
                $order->shift_id
            );
        }

        return $query->exists();
    }

    public function canViewBatch(
        User $user,
        ProductionBatch $batch
    ): bool {
        $batch->loadMissing(
            'productionOrder'
        );

        return $this->canViewOrder(
            $user,
            $batch->productionOrder
        );
    }

    /**
     * Determine whether a user may see one production record.
     */
    public function canViewRecord(
        User $user,
        ProductionRecord $record
    ): bool {
        if (
            $user->can(
                PermissionName
                    ::ViewAllProductionRecords
                    ->value
            )
        ) {
            return true;
        }

        if (
            ! $user->can(
                PermissionName
                    ::ViewOwnProductionRecords
                    ->value
            )
        ) {
            return false;
        }

        $record->loadMissing(
            'operator'
        );

        return $record->operator !== null
            && $record->operator->user_id
                === $user->getKey();
    }

    /**
     * Operators may edit only their own recent draft records.
     *
     * Supervisors and administrators may edit accessible drafts
     * when they possess the submission permission.
     */
    public function canUpdateRecord(
        User $user,
        ProductionRecord $record
    ): bool {
        if (
            $record->status
            !== ProductionRecordStatus::Draft
        ) {
            return false;
        }

        if (
            $user->can(
                PermissionName
                    ::ViewAllProductionRecords
                    ->value
            )
            && $user->can(
                PermissionName
                    ::SubmitProductionRecords
                    ->value
            )
        ) {
            return true;
        }

        if (
            ! $user->can(
                PermissionName
                    ::UpdateRecentProductionRecords
                    ->value
            )
            || ! $this->canViewRecord(
                $user,
                $record
            )
        ) {
            return false;
        }

        $windowHours = max(
            1,
            (int) config(
                'production.operator_record_edit_window_hours',
                48
            )
        );

        $createdAt = $record->created_at;

        if ($createdAt === null) {
            return false;
        }

        return $createdAt->greaterThanOrEqualTo(
            now()->subHours($windowHours)
        );
    }

    public function canSubmitRecord(
        User $user,
        ProductionRecord $record
    ): bool {
        if (
            ! $user->can(
                PermissionName
                    ::SubmitProductionRecords
                    ->value
            )
        ) {
            return false;
        }

        if (
            $user->can(
                PermissionName
                    ::ViewAllProductionRecords
                    ->value
            )
        ) {
            return true;
        }

        return $this->canViewRecord(
            $user,
            $record
        );
    }

    /**
     * Determine whether a production event is inside the user's scope.
     */
    public function canViewEvent(
        User $user,
        ProductionEvent $event
    ): bool {
        if (
            ! $user->can(
                PermissionName
                    ::ViewProductionEvents
                    ->value
            )
        ) {
            return false;
        }

        if (
            $user->hasAnyRole([
                RoleName
                    ::ProductionSupervisor
                    ->value,

                RoleName
                    ::ProductionManager
                    ->value,

                RoleName
                    ::Administrator
                    ->value,
            ])
        ) {
            return true;
        }

        if (
            $user->hasRole(
                RoleName
                    ::MaintenanceManager
                    ->value
            )
        ) {
            return in_array(
                $event->event_type,
                [
                    ProductionEventType::Downtime,

                    ProductionEventType
                        ::MachineIncident,
                ],
                true
            );
        }

        if (
            ! $user->hasRole(
                RoleName::Operator->value
            )
        ) {
            return false;
        }

        if (
            $event->reported_by
            === $user->getKey()
        ) {
            return true;
        }

        $event->loadMissing(
            'operator'
        );

        return $event->operator !== null
            && $event->operator->user_id
                === $user->getKey();
    }

    /**
     * Maintenance managers resolve only maintenance-related events.
     * Supervisors and administrators may resolve all production events.
     */
    public function canResolveEvent(
        User $user,
        ProductionEvent $event
    ): bool {
        if (
            ! $user->can(
                PermissionName
                    ::ResolveProductionEvents
                    ->value
            )
            || $event->is_resolved
        ) {
            return false;
        }

        if (
            $user->hasAnyRole([
                RoleName
                    ::ProductionSupervisor
                    ->value,

                RoleName
                    ::Administrator
                    ->value,
            ])
        ) {
            return true;
        }

        return $user->hasRole(
            RoleName
                ::MaintenanceManager
                ->value
        )
            && in_array(
                $event->event_type,
                [
                    ProductionEventType::Downtime,

                    ProductionEventType
                        ::MachineIncident,
                ],
                true
            );
    }
}