<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\Production\ProductionAccessResolver;

final class ProductionBatchPolicy
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
        ProductionBatch $batch
    ): bool {
        return $this->access->canViewBatch(
            $user,
            $batch
        );
    }

    public function create(
        User $user,
        ProductionOrder $order
    ): bool {
        return $user->can(
            PermissionName
                ::CreateProductionBatches
                ->value
        )
            && $this->access->canViewOrder(
                $user,
                $order
            );
    }

    public function transition(
        User $user,
        ProductionBatch $batch
    ): bool {
        return $user->can(
            PermissionName
                ::ManageProductionBatches
                ->value
        )
            && $this->access->canViewBatch(
                $user,
                $batch
            )
            && ! $batch->status->isTerminal();
    }
}