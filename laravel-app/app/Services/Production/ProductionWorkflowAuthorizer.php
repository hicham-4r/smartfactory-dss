<?php

namespace App\Services\Production;

use App\Enums\Production\ProductionEventType;
use App\Enums\RoleName;
use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class ProductionWorkflowAuthorizer
{
    /**
     * Creating and changing production orders is an operational
     * supervisor responsibility.
     */
    public function assertCanManageOrders(
        User $user
    ): void {
        $this->assertHasAnyRole(
            $user,
            [
                RoleName::ProductionSupervisor,
                RoleName::Administrator,
            ],
            'You are not authorized to manage production orders.'
        );
    }

    /**
     * Batch lifecycle operations use the same operational boundary.
     */
    public function assertCanManageBatches(
        User $user
    ): void {
        $this->assertHasAnyRole(
            $user,
            [
                RoleName::ProductionSupervisor,
                RoleName::Administrator,
            ],
            'You are not authorized to manage production batches.'
        );
    }

    /**
     * Operators may create production records for their own
     * assignments. Supervisors and administrators may create
     * records on behalf of an assigned operator.
     */
    public function assertCanCreateRecord(
        User $user
    ): void {
        $this->assertHasAnyRole(
            $user,
            [
                RoleName::Operator,
                RoleName::ProductionSupervisor,
                RoleName::Administrator,
            ],
            'You are not authorized to create production records.'
        );
    }

    /**
     * Determine whether an operator is working under the restricted
     * operator workflow.
     */
    public function isOperator(
        User $user
    ): bool {
        return $user->hasRole(
            RoleName::Operator->value
        );
    }

    /**
     * Operators may submit only records belonging to their own
     * employee account.
     */
    public function assertCanSubmitRecord(
        User $user,
        ProductionRecord $record
    ): void {
        if (
            $user->hasAnyRole([
                RoleName::ProductionSupervisor->value,
                RoleName::Administrator->value,
            ])
        ) {
            return;
        }

        if (
            $this->isOperator($user)
            && $record->operator !== null
            && $record->operator->user_id
                === $user->getKey()
        ) {
            return;
        }

        throw new AuthorizationException(
            'You are not authorized to submit this production record.'
        );
    }

    /**
     * Only supervisors and administrators may validate or reject
     * submitted production records.
     */
    public function assertCanDecideRecord(
        User $user
    ): void {
        $this->assertHasAnyRole(
            $user,
            [
                RoleName::ProductionSupervisor,
                RoleName::Administrator,
            ],
            'You are not authorized to validate production records.'
        );
    }

    /**
     * Event reporting is restricted by role and event type.
     */
    public function assertCanReportEvent(
        User $user,
        ProductionEventType $eventType
    ): void {
        if (
            $user->hasAnyRole([
                RoleName::ProductionSupervisor->value,
                RoleName::Administrator->value,
            ])
        ) {
            return;
        }

        $operationalEvent = in_array(
            $eventType,
            [
                ProductionEventType::Downtime,
                ProductionEventType::MachineIncident,
                ProductionEventType::Comment,
            ],
            true
        );

        if (
            $operationalEvent
            && $user->hasAnyRole([
                RoleName::Operator->value,
                RoleName::MaintenanceManager->value,
            ])
        ) {
            return;
        }

        throw new AuthorizationException(
            'You are not authorized to report this production event.'
        );
    }

    /**
     * Operators report events, but event resolution remains a
     * supervisor, maintenance or administrator operation.
     */
    public function assertCanResolveEvent(
        User $user
    ): void {
        $this->assertHasAnyRole(
            $user,
            [
                RoleName::ProductionSupervisor,
                RoleName::MaintenanceManager,
                RoleName::Administrator,
            ],
            'You are not authorized to resolve production events.'
        );
    }

    /**
     * @param list<RoleName> $roles
     */
    private function assertHasAnyRole(
        User $user,
        array $roles,
        string $message
    ): void {
        $roleValues = array_map(
            static fn (RoleName $role): string =>
                $role->value,
            $roles
        );

        if ($user->hasAnyRole($roleValues)) {
            return;
        }

        throw new AuthorizationException(
            $message
        );
    }
}