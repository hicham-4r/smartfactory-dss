<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\Production\ProductionEventType;
use App\Enums\RoleName;
use App\Models\ProductionEvent;
use App\Models\User;
use App\Services\Production\ProductionAccessResolver;

final class ProductionEventPolicy
{
    public function __construct(
        private readonly ProductionAccessResolver
            $access
    ) {
    }

    public function viewAny(
        User $user
    ): bool {
        return $user->can(
            PermissionName
                ::ViewProductionEvents
                ->value
        );
    }

    public function view(
        User $user,
        ProductionEvent $event
    ): bool {
        return $this->access->canViewEvent(
            $user,
            $event
        );
    }

    public function report(
        User $user,
        ProductionEventType $eventType
    ): bool {
        if (
            $user->hasAnyRole([
                RoleName
                    ::ProductionSupervisor
                    ->value,

                RoleName
                    ::Administrator
                    ->value,
            ])
            && $user->can(
                PermissionName
                    ::ViewProductionEvents
                    ->value
            )
        ) {
            return true;
        }

        return match ($eventType) {
            ProductionEventType::Downtime =>
                $user->can(
                    PermissionName
                        ::ReportDowntime
                        ->value
                ),

            ProductionEventType::MachineIncident =>
                $user->can(
                    PermissionName
                        ::ReportMachineIncident
                        ->value
                ),

            ProductionEventType::Comment =>
                $user->can(
                    PermissionName
                        ::AddProductionEventComment
                        ->value
                ),

            ProductionEventType::Production,
            ProductionEventType::Quality => false,
        };
    }

    public function resolve(
        User $user,
        ProductionEvent $event
    ): bool {
        return $this->access
            ->canResolveEvent(
                $user,
                $event
            );
    }
}