<?php

namespace Tests\Feature\Production;

use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionImportStatus;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationDecision;
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
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupervisorProductionHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-07-15 10:00:00'
            )
        );

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->seed(
            ProductionWorkflowPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(
            route(
                'production.supervisor.index'
            )
        )->assertRedirect(
            route('login')
        );
    }

    public function test_operator_cannot_access_supervisor_interface(): void
    {
        $operator = $this->userWithRole(
            RoleName::Operator
        );

        $this
            ->actingAs($operator)
            ->get(
                route(
                    'production.supervisor.index'
                )
            )
            ->assertForbidden();
    }

    public function test_production_manager_cannot_access_supervisor_interface(): void
    {
        $manager = $this->userWithRole(
            RoleName::ProductionManager
        );

        $this
            ->actingAs($manager)
            ->get(
                route(
                    'production.supervisor.index'
                )
            )
            ->assertForbidden();
    }

    public function test_supervisor_can_open_dashboard(): void
    {
        $supervisor = $this->supervisor();

        $this
            ->actingAs($supervisor)
            ->get(
                route(
                    'production.supervisor.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'Production Supervisor'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'private'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'max-age=0'
            );
    }

    public function test_supervisor_can_create_draft_order(): void
    {
        $supervisor = $this->supervisor();

        $assignment = $this->assignment();

        $response = $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.orders.store'
                ),
                $this->validOrderPayload(
                    $assignment
                )
            );

        $order = ProductionOrder::query()
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'production.supervisor.orders.show',
                $order
            )
        );

        $this->assertSame(
            ProductionOrderStatus::Draft,
            $order->status
        );

        $this->assertSame(
            $supervisor->getKey(),
            $order->created_by
        );

        $this->assertSame(
            1,
            $order->lock_version
        );
    }

    public function test_invalid_order_window_is_rejected(): void
    {
        $supervisor = $this->supervisor();

        $payload = $this->validOrderPayload(
            $this->assignment()
        );

        $payload['planned_end_at'] =
            '2026-07-15T05:00';

        $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.orders.store'
                ),
                $payload
            )
            ->assertSessionHasErrors(
                'planned_end_at'
            );

        $this->assertDatabaseCount(
            'production_orders',
            0
        );
    }

    public function test_supervisor_can_plan_and_release_order(): void
    {
        $supervisor = $this->supervisor();

        $order = $this->createOrder(
            $this->assignment(),
            ProductionOrderStatus::Draft
        );

        $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.orders.transition',
                    $order
                ),
                [
                    'target_status' =>
                        ProductionOrderStatus
                            ::Planned
                            ->value,

                    'lock_version' => 1,
                ]
            )
            ->assertRedirect(
                route(
                    'production.supervisor.orders.show',
                    $order
                )
            );

        $order->refresh();

        $this->assertSame(
            ProductionOrderStatus::Planned,
            $order->status
        );

        $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.orders.transition',
                    $order
                ),
                [
                    'target_status' =>
                        ProductionOrderStatus
                            ::Released
                            ->value,

                    'lock_version' => 2,
                ]
            )
            ->assertRedirect(
                route(
                    'production.supervisor.orders.show',
                    $order
                )
            );

        $order->refresh();

        $this->assertSame(
            ProductionOrderStatus::Released,
            $order->status
        );

        $this->assertSame(
            3,
            $order->lock_version
        );
    }

    public function test_supervisor_can_create_and_start_batch(): void
    {
        $supervisor = $this->supervisor();

        $order = $this->createOrder(
            $this->assignment(),
            ProductionOrderStatus::Released
        );

        $response = $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.batches.store',
                    $order
                ),
                [
                    'planned_quantity' =>
                        '1000.000',

                    'scheduled_start_at' =>
                        '2026-07-15T06:00',
                ]
            );

        $batch = ProductionBatch::query()
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'production.supervisor.batches.show',
                $batch
            )
        );

        $this->assertSame(
            ProductionBatchStatus::Planned,
            $batch->status
        );

        $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.batches.transition',
                    $batch
                ),
                [
                    'target_status' =>
                        ProductionBatchStatus
                            ::Ready
                            ->value,

                    'lock_version' => 1,
                ]
            )
            ->assertRedirect(
                route(
                    'production.supervisor.batches.show',
                    $batch
                )
            );

        $batch->refresh();

        $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.batches.transition',
                    $batch
                ),
                [
                    'target_status' =>
                        ProductionBatchStatus
                            ::InProgress
                            ->value,

                    'lock_version' => 2,
                ]
            )
            ->assertRedirect(
                route(
                    'production.supervisor.batches.show',
                    $batch
                )
            );

        $batch->refresh();
        $order->refresh();

        $this->assertSame(
            ProductionBatchStatus::InProgress,
            $batch->status
        );

        $this->assertSame(
            ProductionOrderStatus::InProgress,
            $order->status
        );
    }

    public function test_supervisor_can_validate_submitted_record(): void
    {
        $supervisor = $this->supervisor();

        $flow = $this->createExecutionFlow(
            $this->assignment()
        );

        $record = $this->createSubmittedRecord(
            $flow['batch'],
            $flow['assignment']
        );

        $response = $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.records.decide',
                    $record
                ),
                [
                    'decision' =>
                        ProductionValidationDecision
                            ::Validated
                            ->value,

                    'reason' =>
                        'Production values verified.',

                    'lock_version' => 1,
                ]
            );

        $record->refresh();

        $response->assertRedirect(
            route(
                'production.supervisor.records.show',
                $record
            )
        );

        $this->assertSame(
            ProductionRecordStatus::Locked,
            $record->status
        );

        $this->assertSame(
            ProductionValidationStatus::Validated,
            $record->validation_status
        );

        $this->assertDatabaseHas(
            'production_record_validations',
            [
                'production_record_id' =>
                    $record->getKey(),

                'decision' =>
                    ProductionValidationDecision
                        ::Validated
                        ->value,

                'record_version' => 1,
            ]
        );
    }

    public function test_rejection_requires_reason(): void
    {
        $supervisor = $this->supervisor();

        $flow = $this->createExecutionFlow(
            $this->assignment()
        );

        $record = $this->createSubmittedRecord(
            $flow['batch'],
            $flow['assignment']
        );

        $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.records.decide',
                    $record
                ),
                [
                    'decision' =>
                        ProductionValidationDecision
                            ::Rejected
                            ->value,

                    'reason' => '',

                    'lock_version' => 1,
                ]
            )
            ->assertSessionHasErrors(
                'reason'
            );

        $this->assertSame(
            ProductionRecordStatus::Submitted,
            $record->fresh()->status
        );
    }

    public function test_supervisor_can_resolve_open_event(): void
    {
        $supervisor = $this->supervisor();

        $flow = $this->createExecutionFlow(
            $this->assignment()
        );

        $event = $this->createEvent(
            $flow
        );

        $response = $this
            ->actingAs($supervisor)
            ->post(
                route(
                    'production.supervisor.events.resolve',
                    $event
                ),
                [
                    'lock_version' => 1,
                ]
            );

        $event->refresh();

        $response->assertRedirect(
            route(
                'production.supervisor.events.show',
                $event
            )
        );

        $this->assertTrue(
            $event->is_resolved
        );

        $this->assertSame(
            $supervisor->getKey(),
            $event->resolved_by
        );

        $this->assertNotNull(
            $event->resolved_at
        );
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

            'must_change_password' =>
                false,

            'password_changed_at' =>
                now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
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

    /**
     * @return array<string, mixed>
     */
    private function validOrderPayload(
        OperatorAssignment $assignment
    ): array {
        return [
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
                '2026-07-15T06:00',

            'planned_end_at' =>
                '2026-07-15T14:00',

            'target_quantity' =>
                '1000.000',

            'quantity_unit' =>
                'bottles',

            'priority' => 2,

            'instructions' =>
                'Supervisor HTTP test order.',
        ];
    }

    private function createOrder(
        OperatorAssignment $assignment,
        ProductionOrderStatus $status
    ): ProductionOrder {
        $order = new ProductionOrder();

        $order->fill([
            'order_number' =>
                'PO-SUP-'
                .Str::upper(
                    Str::random(10)
                ),

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
            'status' => $status,

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
     *     assignment: OperatorAssignment
     * }
     */
    private function createExecutionFlow(
        OperatorAssignment $assignment
    ): array {
        $order = $this->createOrder(
            $assignment,
            ProductionOrderStatus::InProgress
        );

        $batch = new ProductionBatch();

        $batch->fill([
            'production_order_id' =>
                $order->getKey(),

            'batch_number' =>
                'BAT-SUP-'
                .Str::upper(
                    Str::random(10)
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

        return [
            'order' => $order,
            'batch' => $batch->refresh(),
            'assignment' => $assignment,
        ];
    }

    private function createSubmittedRecord(
        ProductionBatch $batch,
        OperatorAssignment $assignment
    ): ProductionRecord {
        $record = new ProductionRecord();

        $record->fill([
            'record_number' =>
                'PR-SUP-'
                .Str::upper(
                    Str::random(10)
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
            'status' =>
                ProductionRecordStatus
                    ::Submitted,

            'validation_status' =>
                ProductionValidationStatus
                    ::Pending,

            'submitted_at' => now(),

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return $record->refresh();
    }

    /**
     * @param array{
     *     order: ProductionOrder,
     *     batch: ProductionBatch,
     *     assignment: OperatorAssignment
     * } $flow
     */
    private function createEvent(
        array $flow
    ): ProductionEvent {
        $machine = Machine::query()
            ->where(
                'production_line_id',
                $flow['assignment']
                    ->production_line_id
            )
            ->firstOrFail();

        $event = new ProductionEvent();

        $event->fill([
            'event_number' =>
                'EVT-SUP-'
                .Str::upper(
                    Str::random(10)
                ),

            'production_batch_id' =>
                $flow['batch']->getKey(),

            'production_record_id' =>
                null,

            'production_line_id' =>
                $flow['assignment']
                    ->production_line_id,

            'machine_id' =>
                $machine->getKey(),

            'shift_id' =>
                $flow['assignment']
                    ->shift_id,

            'operator_id' =>
                $flow['assignment']
                    ->operator_id,

            'event_type' =>
                ProductionEventType
                    ::MachineIncident,

            'severity' =>
                ProductionEventSeverity
                    ::Warning,

            'title' =>
                'Supervisor HTTP test event',

            'description' =>
                'Synthetic unresolved event.',

            'started_at' =>
                '2026-07-15 09:30:00',

            'duration_minutes' => null,
        ]);

        $event->forceFill([
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