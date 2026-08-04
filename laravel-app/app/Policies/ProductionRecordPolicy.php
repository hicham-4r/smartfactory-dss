<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ProductionBatch;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Services\Production\ProductionAccessResolver;

final class ProductionRecordPolicy
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
                ::ViewOwnProductionRecords
                ->value
        )
            || $user->can(
                PermissionName
                    ::ViewAllProductionRecords
                    ->value
            );
    }

    public function view(
        User $user,
        ProductionRecord $record
    ): bool {
        return $this->access->canViewRecord(
            $user,
            $record
        );
    }

    public function create(
        User $user,
        ProductionBatch $batch
    ): bool {
        return $user->can(
            PermissionName
                ::CreateProductionRecords
                ->value
        )
            && $this->access->canViewBatch(
                $user,
                $batch
            );
    }

    public function update(
        User $user,
        ProductionRecord $record
    ): bool {
        return $this->access->canUpdateRecord(
            $user,
            $record
        );
    }

    public function submit(
        User $user,
        ProductionRecord $record
    ): bool {
        return $this->access->canSubmitRecord(
            $user,
            $record
        )
            && $record->status->isEditable();
    }

    public function validate(
        User $user,
        ProductionRecord $record
    ): bool {
        return $user->can(
            PermissionName
                ::ValidateProductionRecords
                ->value
        )
            && $this->access->canViewRecord(
                $user,
                $record
            )
            && $record->canBeValidated();
    }

    public function reject(
        User $user,
        ProductionRecord $record
    ): bool {
        return $user->can(
            PermissionName
                ::RejectProductionRecords
                ->value
        )
            && $this->access->canViewRecord(
                $user,
                $record
            )
            && $record->canBeValidated();
    }
}