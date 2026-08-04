<?php

namespace Tests\Feature\Production;

use App\Enums\PermissionName;
use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionImportStatus;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\User;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProductionAuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        /*
         * The additive seeder is also tested to guarantee that
         * existing databases can be updated safely.
         */
        $this->seed(
            ProductionWorkflowPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_new_production_permissions_are_assigned_to_correct_roles(): void
    {
        $operator = $this->userWithRole(
            RoleName::Operator
        );

        $supervisor = $this->userWithRole(
            RoleName::ProductionSupervisor
        );

        $manager = $this->userWithRole(
            RoleName::ProductionManager
        );

        $maintenance = $this->userWithRole(
            RoleName::MaintenanceManager
        );

        $this->assertTrue(
            $operator->can(
                PermissionName
                    ::SubmitProductionRecords
                    ->value
            )
        );

        $this->assertFalse(
            $operator->can(
                PermissionName
                    ::CreateProductionOrders
                    ->value
            )
        );

        $this->assertTrue(
            $supervisor->can(
                PermissionName
                    ::CreateProductionOrders
                    ->value
            )
        );

        $this->assertTrue(
            $supervisor->can(
                PermissionName
                    ::ManageProductionBatches
                    ->value
            )
        );

        $this->assertTrue(
            $manager->can(
                PermissionName
                    ::ViewAllProductionOrders
                    ->value
            )
        );

        $this->assertFalse(
            $manager->can(
                PermissionName
                    ::UpdateProductionOrders
                    ->value
            )
        );

        $this->assertTrue(
            $maintenance->can(
                PermissionName
                    ::ResolveProductionEvents
                    ->value
            )
        );
    }

    public function test_operator_can_view_assigned_order_but_not_unassigned_order(): void
    {
        $context = $this->operatorContext(0);

        $assignedOrder = $this->createOrder(
            $context['assignment']
        );

        $otherAssignment =
            $this->assignment(3);

        $unassignedOrder = $this->createOrder(
            $otherAssignment,
            'PO-AUTH-OTHER'
        );

        $this->assertTrue(
            Gate::forUser($context['user'])
                ->allows(
                    'view',
                    $assignedOrder
                )
        );

        $this->assertFalse(
            Gate::forUser($context['user'])
                ->allows(
                    'view',
                    $unassignedOrder
                )
        );
    }

    public function test_production_manager_can_view_all_orders_but_cannot_modify_them(): void
    {
        $manager = $this->userWithRole(
            RoleName::ProductionManager
        );

        $order = $this->createOrder(
            $this->assignment()
        );

        $this->assertTrue(
            Gate::forUser($manager)
                ->allows('view', $order)
        );

        $this->assertFalse(
            Gate::forUser($manager)
                ->allows('update', $order)
        );

        $this->assertFalse(
            Gate::forUser($manager)
                ->allows('release', $order)
        );
    }

    public function test_operator_can_update_own_recent_draft_record(): void
    {
        $context = $this->operatorContext();

        $flow = $this->createFlow(
            $context['assignment'],
            $context['user']
        );

        $this->assertTrue(
            Gate::forUser($context['user'])
                ->allows(
                    'view',
                    $flow['record']
                )
        );

        $this->assertTrue(
            Gate::forUser($context['user'])
                ->allows(
                    'update',
                    $flow['record']
                )
        );

        $this->assertTrue(
            Gate::forUser($context['user'])
                ->allows(
                    'submit',
                    $flow['record']
                )
        );
    }

    public function test_operator_cannot_update_submitted_record(): void
    {
        $context = $this->operatorContext();

        $flow = $this->createFlow(
            $context['assignment'],
            $context['user'],
            ProductionRecordStatus::Submitted
        );

        $this->assertFalse(
            Gate::forUser($context['user'])
                ->allows(
                    'update',
                    $flow['record']
                )
        );

        $this->assertFalse(
            Gate::forUser($context['user'])
                ->allows(
                    'submit',
                    $flow['record']
                )
        );
    }

    public function test_operator_cannot_view_another_operators_record(): void
    {
        $firstContext =
            $this->operatorContext(0);

        $secondContext =
            $this->operatorContext(1);

        $flow = $this->createFlow(
            $secondContext['assignment'],
            $secondContext['user']
        );

        $this->assertFalse(
            Gate::forUser(
                $firstContext['user']
            )->allows(
                'view',
                $flow['record']
            )
        );
    }

    public function test_supervisor_can_validate_and_reject_pending_submission(): void
    {
        $supervisor = $this->userWithRole(
            RoleName::ProductionSupervisor
        );

        $flow = $this->createFlow(
            $this->assignment(),
            $supervisor,
            ProductionRecordStatus::Submitted
        );

        $this->assertTrue(
            Gate::forUser($supervisor)
                ->allows(
                    'validate',
                    $flow['record']
                )
        );

        $this->assertTrue(
            Gate::forUser($supervisor)
                ->allows(
                    'reject',
                    $flow['record']
                )
        );
    }

    public function test_maintenance_manager_can_resolve_machine_incident_but_not_comment(): void
    {
        $maintenance = $this->userWithRole(
            RoleName::MaintenanceManager
        );

        $flow = $this->createFlow(
            $this->assignment(),
            $maintenance
        );

        $machineIncident = $this->createEvent(
            flow: $flow,
            type:
                ProductionEventType
                    ::MachineIncident,
            number:
                'EVT-AUTH-MACHINE'
        );

        $comment = $this->createEvent(
            flow: $flow,
            type:
                ProductionEventType::Comment,
            number:
                'EVT-AUTH-COMMENT'
        );

        $this->assertTrue(
            Gate::forUser($maintenance)
                ->allows(
                    'view',
                    $machineIncident
                )
        );

        $this->assertTrue(
            Gate::forUser($maintenance)
                ->allows(
                    'resolve',
                    $machineIncident
                )
        );

        $this->assertFalse(
            Gate::forUser($maintenance)
                ->allows(
                    'view',
                    $comment
                )
        );

        $this->assertFalse(
            Gate::forUser($maintenance)
                ->allows(
                    'resolve',
                    $comment
                )
        );
    }

    public function test_additive_permission_seeder_is_idempotent(): void
    {
        $this->seed(
            ProductionWorkflowPermissionsSeeder::class
        );

        $this->seed(
            ProductionWorkflowPermissionsSeeder::class
        );

        $supervisor = $this->userWithRole(
            RoleName::ProductionSupervisor
        );

        $this->assertTrue(
            $supervisor->can(
                PermissionName
                    ::CreateProductionOrders
                    ->value
            )
        );

        $this->assertSame(
            1,
            $supervisor
                ->getAllPermissions()
                ->where(
                    'name',
                    PermissionName
                        ::CreateProductionOrders
                        ->value
                )
                ->count()
        );
    }

    /**
     * @return array{
     *     user: User,
     *     assignment: OperatorAssignment
     * }
     */
    private function operatorContext(
        int $offset = 0
    ): array {
        $assignment = $this->assignment(
            $offset
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

    private function userWithRole(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' => true,

            'must_change_password' =>
                false,

            'password_changed_at' => now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }

    private function createOrder(
        OperatorAssignment $assignment,
        string $number = 'PO-AUTH-ASSIGNED'
    ): ProductionOrder {
        $order = new ProductionOrder();

        $order->fill([
            'order_number' => $number,

            'product_id' =>
                Product::query()
                    ->firstOrFail()
                    ->getKey(),

            'production_line_id' =>
                $assignment
                    ->production_line_id,

            'shift_id' =>
                $assignment->shift_id,

            'planned_start_at' =>
                '2026-07-15 06:00:00',

            'planned_end_at' =>
                '2026-07-15 14:00:00',

            'target_quantity' =>
                '1000.000',

            'quantity_unit' =>
                'bottles',

            'priority' => 2,
        ]);

        $order->forceFill([
            'status' =>
                ProductionOrderStatus::Released,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return $order->refresh();
    }

    /**
     * @return array{
     *     order: ProductionOrder,
     *     batch: ProductionBatch,
     *     record: ProductionRecord
     * }
     */
    private function createFlow(
        OperatorAssignment $assignment,
        User $recordedBy,
        ProductionRecordStatus $recordStatus =
            ProductionRecordStatus::Draft
    ): array {
        $order = $this->createOrder(
            $assignment,
            'PO-AUTH-'
            .strtoupper(
                substr(
                    md5(
                        (string) microtime(true)
                    ),
                    0,
                    8
                )
            )
        );

        $batch = new ProductionBatch();

        $batch->fill([
            'production_order_id' =>
                $order->getKey(),

            'batch_number' =>
                'BAT-AUTH-'
                .strtoupper(
                    substr(
                        md5(
                            (string) microtime(true)
                            .'batch'
                        ),
                        0,
                        8
                    )
                ),

            'sequence_number' => 1,

            'planned_quantity' =>
                '1000.000',

            'actual_good_quantity' =>
                '0.000',

            'actual_rejected_quantity' =>
                '0.000',

            'quantity_unit' =>
                'bottles',

            'scheduled_start_at' =>
                '2026-07-15 06:00:00',

            'actual_start_at' =>
                '2026-07-15 06:00:00',
        ]);

        $batch->forceFill([
            'status' =>
                ProductionBatchStatus
                    ::InProgress,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        $record = new ProductionRecord();

        $record->fill([
            'record_number' =>
                'PR-AUTH-'
                .strtoupper(
                    substr(
                        md5(
                            (string) microtime(true)
                            .'record'
                        ),
                        0,
                        8
                    )
                ),

            'production_batch_id' =>
                $batch->getKey(),

            'production_line_id' =>
                $assignment
                    ->production_line_id,

            'shift_id' =>
                $assignment->shift_id,

            'operator_id' =>
                $assignment->operator_id,

            'production_date' =>
                '2026-07-15',

            'started_at' =>
                '2026-07-15 06:00:00',

            'ended_at' =>
                '2026-07-15 14:00:00',

            'produced_quantity' =>
                '1000.000',

            'good_quantity' =>
                '975.000',

            'rejected_quantity' =>
                '25.000',

            'quantity_unit' =>
                'bottles',

            'runtime_minutes' => 450,

            'downtime_minutes' => 30,
        ]);

        $record->forceFill([
            'recorded_by' =>
                $recordedBy->getKey(),

            'updated_by' =>
                $recordedBy->getKey(),

            'status' => $recordStatus,

            'validation_status' =>
                ProductionValidationStatus
                    ::Pending,

            'submitted_at' =>
                $recordStatus
                === ProductionRecordStatus
                    ::Submitted
                    ? now()
                    : null,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return [
            'order' => $order,
            'batch' => $batch,
            'record' => $record->refresh(),
        ];
    }

    private function createEvent(
        array $flow,
        ProductionEventType $type,
        string $number
    ): ProductionEvent {
        $machine = Machine::query()
            ->where(
                'production_line_id',
                $flow['record']
                    ->production_line_id
            )
            ->firstOrFail();

        $event = new ProductionEvent();

        $event->fill([
            'event_number' => $number,

            'production_batch_id' =>
                $flow['batch']->getKey(),

            'production_record_id' =>
                $flow['record']->getKey(),

            'production_line_id' =>
                $flow['record']
                    ->production_line_id,

            'machine_id' =>
                $type
                === ProductionEventType
                    ::MachineIncident
                    ? $machine->getKey()
                    : null,

            'shift_id' =>
                $flow['record']->shift_id,

            'operator_id' =>
                $flow['record']->operator_id,

            'event_type' => $type,

            'severity' =>
                ProductionEventSeverity::Warning,

            'title' =>
                'Authorization test event',

            'description' =>
                'Synthetic policy test.',

            'started_at' =>
                '2026-07-15 10:00:00',

            'duration_minutes' => null,
        ]);

        $event->forceFill([
            'reported_by' =>
                $flow['record']
                    ->recorded_by,

            'is_resolved' => false,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return $event->refresh();
    }
}