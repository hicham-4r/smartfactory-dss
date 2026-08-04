<?php

namespace Tests\Feature\Production;

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
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperatorProductionHttpTest extends TestCase
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
                'production.operator.index'
            )
        )->assertRedirect(
            route('login')
        );
    }

    public function test_operator_sees_assigned_order_but_not_unassigned_order(): void
    {
        $context =
            $this->operatorContext(0);

        $assigned = $this->createFlow(
            $context['assignment'],
            'ASSIGNED'
        );

        $unassigned = $this->createFlow(
            $this->assignment(3),
            'UNASSIGNED'
        );

        $response = $this
            ->actingAs($context['user'])
            ->get(
                route(
                    'production.operator.index',
                    [
                        'reference_date' =>
                            '2026-07-15',
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                $assigned['order']
                    ->order_number
            )
            ->assertDontSee(
                $unassigned['order']
                    ->order_number
            );
    }

    public function test_operator_can_create_record_and_cannot_control_operator_identity(): void
    {
        $context =
            $this->operatorContext(0);

        $otherAssignment =
            $this->assignment(1);

        $flow = $this->createFlow(
            $context['assignment'],
            'CREATE'
        );

        $response = $this
            ->actingAs($context['user'])
            ->post(
                route(
                    'production.operator.records.store',
                    $flow['batch']
                ),
                [
                    ...$this->validRecordPayload(
                        $context['assignment']
                    ),

                    /*
                     * This field is not validated or trusted.
                     * The controller derives operator_id from
                     * the authenticated account.
                     */
                    'operator_id' =>
                        $otherAssignment
                            ->operator_id,
                ]
            );

        $record =
            ProductionRecord::query()
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'production.operator.records.show',
                $record
            )
        );

        $this->assertSame(
            $context[
                'assignment'
            ]->operator_id,
            $record->operator_id
        );

        $this->assertSame(
            $context['user']->getKey(),
            $record->recorded_by
        );

        $this->assertSame(
            ProductionRecordStatus::Draft,
            $record->status
        );
    }

    public function test_inconsistent_quantities_are_rejected(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->createFlow(
            $context['assignment'],
            'INVALID-QTY'
        );

        $payload =
            $this->validRecordPayload(
                $context['assignment']
            );

        $payload['produced_quantity'] =
            '1000.000';

        $payload['good_quantity'] =
            '900.000';

        $payload['rejected_quantity'] =
            '50.000';

        $this
            ->actingAs($context['user'])
            ->from(
                route(
                    'production.operator.records.create',
                    $flow['batch']
                )
            )
            ->post(
                route(
                    'production.operator.records.store',
                    $flow['batch']
                ),
                $payload
            )
            ->assertSessionHasErrors(
                'produced_quantity'
            );

        $this->assertDatabaseCount(
            'production_records',
            0
        );
    }

    public function test_operator_can_submit_own_draft_record(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->createFlow(
            $context['assignment'],
            'SUBMIT'
        );

        $record = $this->createDraftRecord(
            $context['user'],
            $context['assignment'],
            $flow['batch'],
            'OWN'
        );

        $response = $this
            ->actingAs($context['user'])
            ->post(
                route(
                    'production.operator.records.submit',
                    $record
                ),
                [
                    'lock_version' => 1,
                ]
            );

        $record->refresh();

        $response->assertRedirect(
            route(
                'production.operator.records.show',
                $record
            )
        );

        $this->assertSame(
            ProductionRecordStatus::Submitted,
            $record->status
        );

        $this->assertSame(
            2,
            $record->lock_version
        );
    }

    public function test_operator_cannot_submit_another_operators_record(): void
    {
        $firstContext =
            $this->operatorContext(0);

        $secondContext =
            $this->operatorContext(1);

        $flow = $this->createFlow(
            $secondContext['assignment'],
            'OTHER-RECORD'
        );

        $record = $this->createDraftRecord(
            $secondContext['user'],
            $secondContext['assignment'],
            $flow['batch'],
            'OTHER'
        );

        $this
            ->actingAs(
                $firstContext['user']
            )
            ->post(
                route(
                    'production.operator.records.submit',
                    $record
                ),
                [
                    'lock_version' => 1,
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            ProductionRecordStatus::Draft,
            $record->fresh()->status
        );
    }

    public function test_machine_incident_requires_machine(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->createFlow(
            $context['assignment'],
            'INCIDENT-VALIDATION'
        );

        $payload =
            $this->validEventPayload(
                $context['assignment']
            );

        $payload['event_type'] =
            ProductionEventType
                ::MachineIncident
                ->value;

        $payload['machine_id'] = null;

        $this
            ->actingAs($context['user'])
            ->post(
                route(
                    'production.operator.events.store',
                    $flow['batch']
                ),
                $payload
            )
            ->assertSessionHasErrors(
                'machine_id'
            );

        $this->assertDatabaseCount(
            'production_events',
            0
        );
    }

    public function test_operator_can_report_machine_incident(): void
    {
        $context =
            $this->operatorContext();

        $flow = $this->createFlow(
            $context['assignment'],
            'INCIDENT'
        );

        $machine = Machine::query()
            ->where(
                'production_line_id',
                $context[
                    'assignment'
                ]->production_line_id
            )
            ->firstOrFail();

        $payload =
            $this->validEventPayload(
                $context['assignment']
            );

        $payload['event_type'] =
            ProductionEventType
                ::MachineIncident
                ->value;

        $payload['machine_id'] =
            $machine->getKey();

        $response = $this
            ->actingAs($context['user'])
            ->post(
                route(
                    'production.operator.events.store',
                    $flow['batch']
                ),
                $payload
            );

        $event =
            ProductionEvent::query()
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'production.operator.events.show',
                $event
            )
        );

        $this->assertSame(
            $context['user']->getKey(),
            $event->reported_by
        );

        $this->assertSame(
            $context[
                'assignment'
            ]->operator_id,
            $event->operator_id
        );

        $this->assertSame(
            $machine->getKey(),
            $event->machine_id
        );
    }

    public function test_production_manager_cannot_access_operator_interface(): void
    {
        $manager = $this->userWithRole(
            RoleName::ProductionManager
        );

        $this
            ->actingAs($manager)
            ->get(
                route(
                    'production.operator.index'
                )
            )
            ->assertForbidden();
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
        $assignment =
            $this->assignment($offset);

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

            'password_changed_at' =>
                now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }

    /**
     * @return array{
     *     order: ProductionOrder,
     *     batch: ProductionBatch
     * }
     */
    private function createFlow(
        OperatorAssignment $assignment,
        string $suffix
    ): array {
        $unique =
            Str::upper(
                Str::random(8)
            );

        $order = new ProductionOrder();

        $order->fill([
            'order_number' =>
                'PO-HTTP-'.$suffix.'-'.$unique,

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
                ProductionOrderStatus
                    ::InProgress,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        $batch = new ProductionBatch();

        $batch->fill([
            'production_order_id' =>
                $order->getKey(),

            'batch_number' =>
                'BAT-HTTP-'.$suffix.'-'.$unique,

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
            'order' => $order->refresh(),
            'batch' => $batch->refresh(),
        ];
    }

    private function createDraftRecord(
        User $user,
        OperatorAssignment $assignment,
        ProductionBatch $batch,
        string $suffix
    ): ProductionRecord {
        $record = new ProductionRecord();

        $record->fill([
            'record_number' =>
                'PR-HTTP-'
                .$suffix
                .'-'
                .Str::upper(
                    Str::random(8)
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
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'status' =>
                ProductionRecordStatus::Draft,

            'validation_status' =>
                ProductionValidationStatus
                    ::Pending,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return $record->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function validRecordPayload(
        OperatorAssignment $assignment
    ): array {
        return [
            'shift_id' =>
                $assignment->shift_id,

            'production_date' =>
                '2026-07-15',

            'started_at' =>
                '2026-07-15T06:00',

            'ended_at' =>
                '2026-07-15T14:00',

            'produced_quantity' =>
                '1000.000',

            'good_quantity' =>
                '975.000',

            'rejected_quantity' =>
                '25.000',

            'runtime_minutes' => 450,

            'downtime_minutes' => 30,

            'notes' =>
                'Operator HTTP test record.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validEventPayload(
        OperatorAssignment $assignment
    ): array {
        return [
            'shift_id' =>
                $assignment->shift_id,

            'production_record_id' =>
                null,

            'machine_id' => null,

            'event_type' =>
                ProductionEventType
                    ::Downtime
                    ->value,

            'severity' =>
                ProductionEventSeverity
                    ::Warning
                    ->value,

            'title' =>
                'Synthetic operator event',

            'description' =>
                'Operator interface HTTP test.',

            'started_at' =>
                '2026-07-15T10:00',

            'ended_at' =>
                '2026-07-15T10:15',
        ];
    }
}