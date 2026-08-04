<?php

namespace App\Services\User;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperatorAdministrationService
{
    public function __construct(
        private readonly AuditLogService $auditLogs
    ) {
    }

    public function linkAccount(
        Operator $operator,
        User $account,
        User $actor
    ): Operator {
        return DB::transaction(
            function () use (
                $operator,
                $account,
                $actor
            ): Operator {
                $lockedOperator = Operator::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $operator->getKey()
                    );

                $lockedAccount = User::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $account->getKey()
                    );

                if (! $lockedOperator->is_active) {
                    throw ValidationException::withMessages([
                        'user_id' =>
                            'An inactive operator cannot be linked to an account.',
                    ]);
                }

                if (! $lockedAccount->is_active) {
                    throw ValidationException::withMessages([
                        'user_id' =>
                            'The selected DSS account is inactive.',
                    ]);
                }

                if (
                    ! $lockedAccount->hasRole(
                        RoleName::Operator->value
                    )
                ) {
                    throw ValidationException::withMessages([
                        'user_id' =>
                            'The selected DSS account must have the Operator role.',
                    ]);
                }

                $linkedElsewhere = Operator::query()
                    ->where(
                        'user_id',
                        $lockedAccount->getKey()
                    )
                    ->where(
                        'id',
                        '!=',
                        $lockedOperator->getKey()
                    )
                    ->lockForUpdate()
                    ->exists();

                if ($linkedElsewhere) {
                    throw ValidationException::withMessages([
                        'user_id' =>
                            'The selected DSS account is already linked to another operator.',
                    ]);
                }

                $oldUserId = $lockedOperator->user_id;

                if (
                    $oldUserId !== null
                    && (int) $oldUserId
                        !== (int) $lockedAccount->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'user_id' =>
                            'This operator is already linked to another DSS account. Unlink it first.',
                    ]);
                }

                $lockedOperator->forceFill([
                    'user_id' =>
                        $lockedAccount->getKey(),
                ])->save();

                $this->auditLogs->record(
                    action:
                        AuditAction::OperatorAccountLinked,

                    actor:
                        $actor,

                    auditable:
                        $lockedOperator,

                    oldValues: [
                        'user_id' => $oldUserId,
                    ],

                    newValues: [
                        'user_id' =>
                            $lockedAccount->getKey(),
                        'user_email' =>
                            $lockedAccount->email,
                    ]
                );

                return $lockedOperator->fresh([
                    'user.roles',
                    'assignments',
                ]);
            },
            attempts: 3
        );
    }

    public function unlinkAccount(
        Operator $operator,
        User $actor
    ): Operator {
        return DB::transaction(
            function () use (
                $operator,
                $actor
            ): Operator {
                $lockedOperator = Operator::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $operator->getKey()
                    );

                if ($lockedOperator->user_id === null) {
                    throw ValidationException::withMessages([
                        'account' =>
                            'This operator is not linked to a DSS account.',
                    ]);
                }

                $oldUserId =
                    (int) $lockedOperator->user_id;

                $lockedOperator->forceFill([
                    'user_id' => null,
                ])->save();

                $this->auditLogs->record(
                    action:
                        AuditAction::OperatorAccountUnlinked,

                    actor:
                        $actor,

                    auditable:
                        $lockedOperator,

                    oldValues: [
                        'user_id' => $oldUserId,
                    ],

                    newValues: [
                        'user_id' => null,
                    ]
                );

                return $lockedOperator->fresh([
                    'user.roles',
                    'assignments',
                ]);
            },
            attempts: 3
        );
    }

    /**
     * @param array{
     *     production_line_id:int,
     *     shift_id:int,
     *     starts_on:string,
     *     ends_on?:string|null,
     *     is_primary?:bool|int|string
     * } $data
     */
    public function createAssignment(
        Operator $operator,
        array $data,
        User $actor
    ): OperatorAssignment {
        return DB::transaction(
            function () use (
                $operator,
                $data,
                $actor
            ): OperatorAssignment {
                $lockedOperator = Operator::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $operator->getKey()
                    );

                if (! $lockedOperator->is_active) {
                    throw ValidationException::withMessages([
                        'operator' =>
                            'An inactive operator cannot receive a new assignment.',
                    ]);
                }

                $line = ProductionLine::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $data[
                            'production_line_id'
                        ]
                    );

                $shift = Shift::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $data['shift_id']
                    );

                $this->assertActiveMasterData(
                    $line,
                    $shift
                );

                $startsOn = CarbonImmutable::parse(
                    (string) $data['starts_on']
                )->startOfDay();

                $endsOn = $this->dateOrNull(
                    $data['ends_on'] ?? null
                );

                $isPrimary = filter_var(
                    $data['is_primary'] ?? false,
                    FILTER_VALIDATE_BOOL
                );

                $this->assertAssignmentPeriod(
                    $startsOn,
                    $endsOn
                );

                $this->assertNoConflicts(
                    operatorId:
                        (int) $lockedOperator->getKey(),

                    lineId:
                        (int) $line->getKey(),

                    shiftId:
                        (int) $shift->getKey(),

                    startsOn:
                        $startsOn,

                    endsOn:
                        $endsOn,

                    isPrimary:
                        $isPrimary,
                );

                $assignment =
                    new OperatorAssignment();

                $assignment->forceFill([
                    'operator_id' =>
                        $lockedOperator->getKey(),

                    'production_line_id' =>
                        $line->getKey(),

                    'shift_id' =>
                        $shift->getKey(),

                    'assigned_by' =>
                        $actor->getKey(),

                    'starts_on' =>
                        $startsOn->toDateString(),

                    'ends_on' =>
                        $endsOn?->toDateString(),

                    'is_primary' =>
                        $isPrimary,

                    'is_active' =>
                        true,

                    'source_system' =>
                        'manual_dss',

                    'external_id' =>
                        null,

                    'source_version' =>
                        null,

                    'source_checksum' =>
                        null,

                    'source_updated_at' =>
                        null,

                    'last_synced_at' =>
                        null,
                ]);

                $assignment->save();

                $this->auditLogs->record(
                    action:
                        AuditAction::OperatorAssignmentCreated,

                    actor:
                        $actor,

                    auditable:
                        $assignment,

                    newValues:
                        $this->assignmentAuditValues(
                            $assignment
                        )
                );

                return $assignment->fresh([
                    'operator.user',
                    'productionLine',
                    'shift',
                    'assignedBy',
                ]);
            },
            attempts: 3
        );
    }

    /**
     * @param array{
     *     production_line_id:int,
     *     shift_id:int,
     *     starts_on:string,
     *     ends_on?:string|null,
     *     is_primary?:bool|int|string
     * } $data
     */
    public function updateAssignment(
        Operator $operator,
        OperatorAssignment $assignment,
        array $data,
        User $actor
    ): OperatorAssignment {
        return DB::transaction(
            function () use (
                $operator,
                $assignment,
                $data,
                $actor
            ): OperatorAssignment {
                $lockedAssignment =
                    OperatorAssignment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $assignment->getKey()
                        );

                $this->assertAssignmentBelongsToOperator(
                    $lockedAssignment,
                    $operator
                );

                $this->assertManualAssignment(
                    $lockedAssignment
                );

                if (! $lockedAssignment->is_active) {
                    throw ValidationException::withMessages([
                        'assignment' =>
                            'An inactive assignment cannot be edited.',
                    ]);
                }

                $line = ProductionLine::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $data[
                            'production_line_id'
                        ]
                    );

                $shift = Shift::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        (int) $data['shift_id']
                    );

                $this->assertActiveMasterData(
                    $line,
                    $shift
                );

                $startsOn = CarbonImmutable::parse(
                    (string) $data['starts_on']
                )->startOfDay();

                $endsOn = $this->dateOrNull(
                    $data['ends_on'] ?? null
                );

                $isPrimary = filter_var(
                    $data['is_primary'] ?? false,
                    FILTER_VALIDATE_BOOL
                );

                $this->assertAssignmentPeriod(
                    $startsOn,
                    $endsOn
                );

                $this->assertNoConflicts(
                    operatorId:
                        (int) $operator->getKey(),

                    lineId:
                        (int) $line->getKey(),

                    shiftId:
                        (int) $shift->getKey(),

                    startsOn:
                        $startsOn,

                    endsOn:
                        $endsOn,

                    isPrimary:
                        $isPrimary,

                    exceptAssignmentId:
                        (int) $lockedAssignment->getKey(),
                );

                $oldValues =
                    $this->assignmentAuditValues(
                        $lockedAssignment
                    );

                $lockedAssignment->forceFill([
                    'production_line_id' =>
                        $line->getKey(),

                    'shift_id' =>
                        $shift->getKey(),

                    'starts_on' =>
                        $startsOn->toDateString(),

                    'ends_on' =>
                        $endsOn?->toDateString(),

                    'is_primary' =>
                        $isPrimary,

                    'assigned_by' =>
                        $actor->getKey(),
                ])->save();

                $this->auditLogs->record(
                    action:
                        AuditAction::OperatorAssignmentUpdated,

                    actor:
                        $actor,

                    auditable:
                        $lockedAssignment,

                    oldValues:
                        $oldValues,

                    newValues:
                        $this->assignmentAuditValues(
                            $lockedAssignment
                        )
                );

                return $lockedAssignment->fresh([
                    'operator.user',
                    'productionLine',
                    'shift',
                    'assignedBy',
                ]);
            },
            attempts: 3
        );
    }

    public function endAssignment(
        Operator $operator,
        OperatorAssignment $assignment,
        CarbonImmutable $endsOn,
        User $actor
    ): OperatorAssignment {
        return DB::transaction(
            function () use (
                $operator,
                $assignment,
                $endsOn,
                $actor
            ): OperatorAssignment {
                $lockedAssignment =
                    OperatorAssignment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $assignment->getKey()
                        );

                $this->assertAssignmentBelongsToOperator(
                    $lockedAssignment,
                    $operator
                );

                $this->assertManualAssignment(
                    $lockedAssignment
                );

                if (! $lockedAssignment->is_active) {
                    throw ValidationException::withMessages([
                        'assignment' =>
                            'This assignment is already inactive.',
                    ]);
                }

                $start = CarbonImmutable::instance(
                    $lockedAssignment->starts_on
                )->startOfDay();

                $effectiveEnd =
                    $endsOn->startOfDay();

                if (
                    $effectiveEnd->lessThan($start)
                    || $effectiveEnd->isFuture()
                ) {
                    throw ValidationException::withMessages([
                        'ends_on' =>
                            'The ending date must be between the assignment start date and today.',
                    ]);
                }

                $oldValues =
                    $this->assignmentAuditValues(
                        $lockedAssignment
                    );

                $lockedAssignment->forceFill([
                    'ends_on' =>
                        $effectiveEnd->toDateString(),

                    'is_active' =>
                        false,

                    'assigned_by' =>
                        $actor->getKey(),
                ])->save();

                $this->auditLogs->record(
                    action:
                        AuditAction::OperatorAssignmentEnded,

                    actor:
                        $actor,

                    auditable:
                        $lockedAssignment,

                    oldValues:
                        $oldValues,

                    newValues:
                        $this->assignmentAuditValues(
                            $lockedAssignment
                        )
                );

                return $lockedAssignment->fresh([
                    'operator.user',
                    'productionLine',
                    'shift',
                    'assignedBy',
                ]);
            },
            attempts: 3
        );
    }

    private function assertActiveMasterData(
        ProductionLine $line,
        Shift $shift
    ): void {
        if (! $line->is_active) {
            throw ValidationException::withMessages([
                'production_line_id' =>
                    'The selected production line is inactive.',
            ]);
        }

        if (! $shift->is_active) {
            throw ValidationException::withMessages([
                'shift_id' =>
                    'The selected shift is inactive.',
            ]);
        }
    }

    private function assertAssignmentPeriod(
        CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn
    ): void {
        if (
            $endsOn !== null
            && $endsOn->lessThan($startsOn)
        ) {
            throw ValidationException::withMessages([
                'ends_on' =>
                    'The ending date cannot precede the starting date.',
            ]);
        }
    }

    private function assertNoConflicts(
        int $operatorId,
        int $lineId,
        int $shiftId,
        CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn,
        bool $isPrimary,
        ?int $exceptAssignmentId = null,
    ): void {
        $sameSlot = OperatorAssignment::query()
            ->where(
                'operator_id',
                $operatorId
            )
            ->where(
                'production_line_id',
                $lineId
            )
            ->where(
                'shift_id',
                $shiftId
            )
            ->where(
                'is_active',
                true
            );

        $this->applyOverlapScope(
            $sameSlot,
            $startsOn,
            $endsOn
        );

        if ($exceptAssignmentId !== null) {
            $sameSlot->where(
                'id',
                '!=',
                $exceptAssignmentId
            );
        }

        if ($sameSlot->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'assignment' =>
                    'An active assignment already covers this operator, production line, shift and period.',
            ]);
        }

        if (! $isPrimary) {
            return;
        }

        $primary = OperatorAssignment::query()
            ->where(
                'operator_id',
                $operatorId
            )
            ->where(
                'is_primary',
                true
            )
            ->where(
                'is_active',
                true
            );

        $this->applyOverlapScope(
            $primary,
            $startsOn,
            $endsOn
        );

        if ($exceptAssignmentId !== null) {
            $primary->where(
                'id',
                '!=',
                $exceptAssignmentId
            );
        }

        if ($primary->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'is_primary' =>
                    'The operator already has another primary assignment during this period.',
            ]);
        }
    }

    private function applyOverlapScope(
        $query,
        CarbonImmutable $startsOn,
        ?CarbonImmutable $endsOn
    ): void {
        if ($endsOn !== null) {
            $query->whereDate(
                'starts_on',
                '<=',
                $endsOn->toDateString()
            );
        }

        $query->where(
            function ($scope) use (
                $startsOn
            ): void {
                $scope
                    ->whereNull('ends_on')
                    ->orWhereDate(
                        'ends_on',
                        '>=',
                        $startsOn->toDateString()
                    );
            }
        );
    }

    private function assertAssignmentBelongsToOperator(
        OperatorAssignment $assignment,
        Operator $operator
    ): void {
        if (
            (int) $assignment->operator_id
            !== (int) $operator->getKey()
        ) {
            abort(
                404,
                'The assignment does not belong to this operator.'
            );
        }
    }

    private function assertManualAssignment(
        OperatorAssignment $assignment
    ): void {
        if (
            ! in_array(
                $assignment->source_system,
                [
                    'manual',
                    'manual_dss',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'assignment' =>
                    'ERP-synchronized assignments are read-only. Create a manual DSS assignment instead.',
            ]);
        }
    }

    private function dateOrNull(
        mixed $value
    ): ?CarbonImmutable {
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        return CarbonImmutable::parse(
            $value
        )->startOfDay();
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    private function assignmentAuditValues(
        OperatorAssignment $assignment
    ): array {
        return [
            'operator_id' =>
                (int) $assignment->operator_id,

            'production_line_id' =>
                (int) $assignment->production_line_id,

            'shift_id' =>
                (int) $assignment->shift_id,

            'starts_on' =>
                $assignment->starts_on
                    ?->toDateString(),

            'ends_on' =>
                $assignment->ends_on
                    ?->toDateString(),

            'is_primary' =>
                (bool) $assignment->is_primary,

            'is_active' =>
                (bool) $assignment->is_active,

            'source_system' =>
                $assignment->source_system,
        ];
    }
}
