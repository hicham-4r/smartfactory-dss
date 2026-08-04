<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\Production\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionAccessResolver;

final class ProductionOrderPolicy
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
                ::ViewAllProductionOrders
                ->value
        )
            || $user->can(
                PermissionName
                    ::ViewAssignedProductionOrders
                    ->value
            );
    }

    public function view(
        User $user,
        ProductionOrder $order
    ): bool {
        return $this->access->canViewOrder(
            $user,
            $order
        );
    }

    public function create(
        User $user
    ): bool {
        return $user->can(
            PermissionName
                ::CreateProductionOrders
                ->value
        );
    }

    public function update(
        User $user,
        ProductionOrder $order
    ): bool {
        return $user->can(
            PermissionName
                ::UpdateProductionOrders
                ->value
        )
            && ! $order->status->isTerminal();
    }

    public function release(
        User $user,
        ProductionOrder $order
    ): bool {
        return $user->can(
            PermissionName
                ::ReleaseProductionOrders
                ->value
        )
            && $order->status
                === ProductionOrderStatus::Planned;
    }

    public function cancel(
        User $user,
        ProductionOrder $order
    ): bool {
        return $user->can(
            PermissionName
                ::CancelProductionOrders
                ->value
        )
            && $order->status->canTransitionTo(
                ProductionOrderStatus::Cancelled
            );
    }
}