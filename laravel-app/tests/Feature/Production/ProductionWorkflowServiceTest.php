<?php

namespace Tests\Feature\Production;

use App\DTOs\Production\CreateProductionBatchData;
use App\DTOs\Production\CreateProductionEventData;
use App\DTOs\Production\CreateProductionOrderData;
use App\DTOs\Production\CreateProductionRecordData;
use App\Enums\AuditAction;
use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationDecision;
use App\Enums\Production\ProductionValidationStatus;
use App\Enums\RoleName;
use App\Exceptions\Production\ProductionWorkflowException;
use App\Models\Machine;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Services\Production\ProductionWorkflowService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $this->workflow = app(
            ProductionWorkflowService::class
        );
    }

    public function test_supervisor_can_create_release_order_and_create_batch(): void
    {
        $supervisor =
            $this->supervisor();

        $assignment =
            $this->assignment();

        $order = $this->workflow
            ->createOrder(
                $supervisor,
                $this->orderData(
                    $assignment
                )
            );

        $this->assertSame(
            ProductionOrderStatus::Draft,
            $order->status
        );

        $order = $this->workflow
            ->transitionOrder(
                $supervisor,
                $order->getKey(),
                ProductionOrderStatus::Planned,
                1
            );

        $order = $this->workflow
            ->transitionOrder(
                $supervisor,
                $order->getKey(),
                ProductionOrderStatus::Released,
                2
            );

        $batch = $this->workflow
            ->createBatch(
                $supervisor,
                new CreateProductionBatchData(
                    productionOrderId:
                        $order->getKey(),

                    plannedQuantity:
                        '1000.000',

                    scheduledStartAt:
                        CarbonImmutable::parse(
                            '2026-07-15 06:00:00'
                        ),

                    quantityUnit:
                        'bottles'
                )
            );

        $this->assertSame(
            ProductionOrderStatus::Released,
            $order->status
        );

        $this->assertSame(
            ProductionBatchStatus::Planned,
            $batch->status
        );

        $this->assertSame(
            1,
            $batch->sequence_number
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction
                        ::ProductionOrderCreated
                        ->value,

                'auditable_id' =>
                    (string) $order->getKey(),
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction
                        ::ProductionBatchCreated
                        ->value,

                'auditable_id' =>
                    (string) $batch->getKey(),
            ]
        );
    }

    public function test_operator_cannot_create_production_order(): void
    {
        $context =
            $this->operatorContext();

        $this->expectException(
            AuthorizationException::class
        );

        $this->workflow->createOrder(
            $context['user'],
            $this->orderData(
                $context['assignment']
            )
        );
    }

    public function test_starting_batch_moves_order_to_in_progress(): void
    {
        $flow =
            $this->inProgressBatch();

        $this->assertSame(
            ProductionBatchStatus::InProgress,
            $flow['batch']->status
        );

        $flow['order']->refresh();

        $this->assertSame(
            ProductionOrderStatus::InProgress,
            $flow['order']->status
        );

        $this->assertNotNull(
            $flow['batch']->actual_start_at
        );
    }

    public function test_assigned_operator_can_create_and_submit_record(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->inProgressBatch(
            $context['assignment']
        );

        $record = $this->createRecord(
            actor: $context['user'],
            batch: $flow['batch'],
            assignment:
                $context['assignment']
        );

        $this->assertSame(
            ProductionRecordStatus::Draft,
            $record->status
        );

        $submitted =
            $this->workflow->submitRecord(
                $context['user'],
                $record->getKey(),
                1
            );

        $this->assertSame(
            ProductionRecordStatus::Submitted,
            $submitted->status
        );

        $this->assertSame(
            ProductionValidationStatus::Pending,
            $submitted->validation_status
        );

        $this->assertSame(
            2,
            $submitted->lock_version
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction
                        ::ProductionRecordSubmitted
                        ->value,

                'auditable_id' =>
                    (string) $record->getKey(),
            ]
        );
    }

    public function test_unassigned_operator_cannot_create_record_for_another_line(): void
    {
        $correctAssignment =
            $this->assignment(0);

        $otherContext =
            $this->operatorContext(3);

        $flow = $this->inProgressBatch(
            $correctAssignment
        );

        $this->expectException(
            AuthorizationException::class
        );

        $this->createRecord(
            actor: $otherContext['user'],
            batch: $flow['batch'],
            assignment:
                $correctAssignment,
            operatorId:
                $otherContext[
                    'assignment'
                ]->operator_id
        );
    }

    public function test_supervisor_can_validate_and_lock_submitted_record(): void
    {
        $supervisor =
            $this->supervisor();

        $context =
            $this->operatorContext();

        $flow = $this->inProgressBatch(
            $context['assignment']
        );

        $record = $this->createRecord(
            actor: $context['user'],
            batch: $flow['batch'],
            assignment:
                $context['assignment']
        );

        $record =
            $this->workflow->submitRecord(
                $context['user'],
                $record->getKey(),
                1
            );

        $validated =
            $this->workflow->decideRecord(
                actor: $supervisor,
                recordId:
                    $record->getKey(),
                decision:
                    ProductionValidationDecision
                        ::Validated,
                expectedVersion: 2,
                reason:
                    'Quantities and timeline verified.'
            );

        $this->assertSame(
            ProductionRecordStatus::Locked,
            $validated->status
        );

        $this->assertSame(
            ProductionValidationStatus::Validated,
            $validated->validation_status
        );

        $this->assertNotNull(
            $validated->locked_at
        );

        $this->assertDatabaseHas(
            'production_record_validations',
            [
                'production_record_id' =>
                    $validated->getKey(),

                'decision' =>
                    ProductionValidationDecision
                        ::Validated
                        ->value,

                'record_version' => 2,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    AuditAction
                        ::ProductionRecordValidated
                        ->value,

                'auditable_id' =>
                    (string) $validated->getKey(),
            ]
        );
    }

    public function test_rejection_requires_reason_and_returns_record_to_draft(): void
    {
        $supervisor =
            $this->supervisor();

        $context =
            $this->operatorContext();

        $flow = $this->inProgressBatch(
            $context['assignment']
        );

        $record = $this->createRecord(
            actor: $context['user'],
            batch: $flow['batch'],
            assignment:
                $context['assignment']
        );

        $record =
            $this->workflow->submitRecord(
                $context['user'],
                $record->getKey(),
                1
            );

        try {
            $this->workflow->decideRecord(
                actor: $supervisor,
                recordId:
                    $record->getKey(),
                decision:
                    ProductionValidationDecision
                        ::Rejected,
                expectedVersion: 2,
                reason: null
            );

            $this->fail(
                'A rejection without a reason was accepted.'
            );
        } catch (
            ProductionWorkflowException $exception
        ) {
            $this->assertSame(
                'A rejection reason is required.',
                $exception->getMessage()
            );
        }

        $rejected =
            $this->workflow->decideRecord(
                actor: $supervisor,
                recordId:
                    $record->getKey(),
                decision:
                    ProductionValidationDecision
                        ::Rejected,
                expectedVersion: 2,
                reason:
                    'The rejected quantity requires verification.'
            );

        $this->assertSame(
            ProductionRecordStatus::Draft,
            $rejected->status
        );

        $this->assertSame(
            ProductionValidationStatus::Rejected,
            $rejected->validation_status
        );
    }

    public function test_operator_cannot_validate_production_record(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->inProgressBatch(
            $context['assignment']
        );

        $record = $this->createRecord(
            actor: $context['user'],
            batch: $flow['batch'],
            assignment:
                $context['assignment']
        );

        $record =
            $this->workflow->submitRecord(
                $context['user'],
                $record->getKey(),
                1
            );

        $this->expectException(
            AuthorizationException::class
        );

        $this->workflow->decideRecord(
            actor: $context['user'],
            recordId: $record->getKey(),
            decision:
                ProductionValidationDecision
                    ::Validated,
            expectedVersion: 2,
            reason: null
        );
    }

    public function test_machine_incident_can_be_reported_and_resolved(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->inProgressBatch(
            $context['assignment']
        );

        $machine = Machine::query()
            ->where(
                'production_line_id',
                $context[
                    'assignment'
                ]->production_line_id
            )
            ->firstOrFail();

        $event = $this->workflow
            ->createEvent(
                $context['user'],

                new CreateProductionEventData(
                    productionBatchId:
                        $flow['batch']
                            ->getKey(),

                    productionRecordId:
                        null,

                    machineId:
                        $machine->getKey(),

                    shiftId:
                        $context[
                            'assignment'
                        ]->shift_id,

                    operatorId:
                        $context[
                            'assignment'
                        ]->operator_id,

                    eventType:
                        ProductionEventType
                            ::MachineIncident,

                    severity:
                        ProductionEventSeverity
                            ::Warning,

                    title:
                        'Temporary filler interruption',

                    description:
                        'Synthetic workflow test incident.',

                    startedAt:
                        CarbonImmutable::parse(
                            '2026-07-15 10:00:00'
                        ),

                    endedAt:
                        CarbonImmutable::parse(
                            '2026-07-15 10:15:00'
                        ),
                )
            );

        $this->assertFalse(
            $event->is_resolved
        );

        $maintenanceManager =
            $this->userWithRole(
                RoleName::MaintenanceManager
            );

        $resolved =
            $this->workflow->resolveEvent(
                $maintenanceManager,
                $event->getKey(),
                1
            );

        $this->assertTrue(
            $resolved->is_resolved
        );

        $this->assertSame(
            $maintenanceManager->getKey(),
            $resolved->resolved_by
        );

        $this->assertSame(
            15,
            $resolved->duration_minutes
        );
    }

    public function test_validated_records_are_aggregated_when_batch_completes(): void
    {
        $supervisor =
            $this->supervisor();

        $context =
            $this->operatorContext();

        $flow = $this->inProgressBatch(
            $context['assignment']
        );

        $record = $this->createRecord(
            actor: $context['user'],
            batch: $flow['batch'],
            assignment:
                $context['assignment']
        );

        $record =
            $this->workflow->submitRecord(
                $context['user'],
                $record->getKey(),
                1
            );

        $this->workflow->decideRecord(
            actor: $supervisor,
            recordId: $record->getKey(),
            decision:
                ProductionValidationDecision
                    ::Validated,
            expectedVersion: 2,
            reason:
                'Validated before batch closure.'
        );

        $completed =
            $this->workflow->transitionBatch(
                actor: $supervisor,
                batchId:
                    $flow['batch']->getKey(),
                target:
                    ProductionBatchStatus
                        ::Completed,
                expectedVersion: 3
            );

        $this->assertSame(
            ProductionBatchStatus::Completed,
            $completed->status
        );

        $this->assertSame(
            '975.000',
            $completed->actual_good_quantity
        );

        $this->assertSame(
            '25.000',
            $completed
                ->actual_rejected_quantity
        );

        $flow['order']->refresh();

        $this->assertSame(
            ProductionOrderStatus::Completed,
            $flow['order']->status
        );
    }

    /**
     * @return array{
     *     user: User,
     *     assignment: OperatorAssignment
     * }
     */
    private function operatorContext(
        int $assignmentOffset = 0
    ): array {
        $assignment =
            $this->assignment(
                $assignmentOffset
            );

        $user = $this->userWithRole(
            RoleName::Operator
        );

        $assignment->operator
            ->forceFill([
                'user_id' =>
                    $user->getKey(),
            ])
            ->save();

        $assignment->refresh();

        return [
            'user' => $user,
            'assignment' => $assignment,
        ];
    }

    private function assignment(
        int $offset = 0
    ): OperatorAssignment {
        return OperatorAssignment::query()
            ->with([
                'operator',
                'productionLine',
                'shift',
            ])
            ->orderBy('id')
            ->skip($offset)
            ->firstOrFail();
    }

    private function supervisor(): User
    {
        return $this->userWithRole(
            RoleName::ProductionSupervisor
        );
    }

    private function userWithRole(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }

    private function orderData(
        OperatorAssignment $assignment
    ): CreateProductionOrderData {
        return new CreateProductionOrderData(
            productId:
                Product::query()
                    ->firstOrFail()
                    ->getKey(),

            productionLineId:
                $assignment
                    ->production_line_id,

            shiftId:
                $assignment->shift_id,

            plannedStartAt:
                CarbonImmutable::parse(
                    '2026-07-15 06:00:00'
                ),

            plannedEndAt:
                CarbonImmutable::parse(
                    '2026-07-15 14:00:00'
                ),

            targetQuantity:
                '1000.000',

            quantityUnit:
                'bottles',

            priority: 2,

            instructions:
                'Synthetic production workflow test.'
        );
    }

    /**
     * @return array{
     *     order: ProductionOrder,
     *     batch: ProductionBatch,
     *     assignment: OperatorAssignment
     * }
     */
    private function inProgressBatch(
        ?OperatorAssignment $assignment = null
    ): array {
        $assignment ??=
            $this->assignment();

        $supervisor =
            $this->supervisor();

        $order = $this->workflow
            ->createOrder(
                $supervisor,
                $this->orderData(
                    $assignment
                )
            );

        $order = $this->workflow
            ->transitionOrder(
                $supervisor,
                $order->getKey(),
                ProductionOrderStatus::Planned,
                1
            );

        $order = $this->workflow
            ->transitionOrder(
                $supervisor,
                $order->getKey(),
                ProductionOrderStatus::Released,
                2
            );

        $batch = $this->workflow
            ->createBatch(
                $supervisor,

                new CreateProductionBatchData(
                    productionOrderId:
                        $order->getKey(),

                    plannedQuantity:
                        '1000.000',

                    scheduledStartAt:
                        CarbonImmutable::parse(
                            '2026-07-15 06:00:00'
                        ),

                    quantityUnit:
                        'bottles'
                )
            );

        $batch = $this->workflow
            ->transitionBatch(
                $supervisor,
                $batch->getKey(),
                ProductionBatchStatus::Ready,
                1
            );

        $batch = $this->workflow
            ->transitionBatch(
                $supervisor,
                $batch->getKey(),
                ProductionBatchStatus::InProgress,
                2
            );

        $order->refresh();

        return [
            'order' => $order,
            'batch' => $batch,
            'assignment' => $assignment,
        ];
    }

    private function createRecord(
        User $actor,
        ProductionBatch $batch,
        OperatorAssignment $assignment,
        ?int $operatorId = null
    ): ProductionRecord {
        return $this->workflow
            ->createRecord(
                $actor,

                new CreateProductionRecordData(
                    productionBatchId:
                        $batch->getKey(),

                    shiftId:
                        $assignment->shift_id,

                    operatorId:
                        $operatorId
                        ?? $assignment
                            ->operator_id,

                    productionDate:
                        CarbonImmutable::parse(
                            '2026-07-15'
                        ),

                    startedAt:
                        CarbonImmutable::parse(
                            '2026-07-15 06:00:00'
                        ),

                    endedAt:
                        CarbonImmutable::parse(
                            '2026-07-15 14:00:00'
                        ),

                    producedQuantity:
                        '1000.000',

                    goodQuantity:
                        '975.000',

                    rejectedQuantity:
                        '25.000',

                    quantityUnit:
                        'bottles',

                    runtimeMinutes: 450,

                    downtimeMinutes: 30,

                    notes:
                        'Synthetic workflow test record.'
                )
            );
    }
}