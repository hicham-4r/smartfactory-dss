<?php

namespace Tests\Feature\Notifications;

use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionImportStatus;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProductionEventNotificationObserverTest extends TestCase
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

    public function test_warning_machine_incident_notifies_supervisor_and_maintenance_only(): void
    {
        $users =
            $this->roleUsers();

        $event =
            $this->createEvent(
                operator: $users['operator'],
                type:
                    ProductionEventType
                        ::MachineIncident,
                severity:
                    ProductionEventSeverity
                        ::Warning,
            );

        $this->assertNotification(
            user: $users['supervisor'],
            category: 'production-event',
            title: 'Machine incident reported',
            actionUrl:
                route(
                    'production.supervisor.events.show',
                    $event,
                    false
                )
        );

        $this->assertNotification(
            user: $users['maintenance'],
            category: 'maintenance-incident',
            title: 'Machine incident reported',
            actionUrl: '/dashboard'
        );

        $this->assertNotificationCount(
            0,
            $users['manager']
        );

        $this->assertNotificationCount(
            0,
            $users['administrator']
        );

        $this->assertNotificationCount(
            0,
            $users['operator']
        );
    }

    public function test_critical_downtime_is_escalated_to_all_required_roles(): void
    {
        $users =
            $this->roleUsers();

        $event =
            $this->createEvent(
                operator: $users['operator'],
                type:
                    ProductionEventType
                        ::Downtime,
                severity:
                    ProductionEventSeverity
                        ::Critical,
            );

        $this->assertNotification(
            user: $users['supervisor'],
            category: 'production-event',
            title: 'Production downtime reported',
            actionUrl:
                route(
                    'production.supervisor.events.show',
                    $event,
                    false
                )
        );

        $this->assertNotification(
            user: $users['maintenance'],
            category: 'maintenance-incident',
            title: 'Production downtime reported',
            actionUrl: '/dashboard'
        );

        $this->assertNotification(
            user: $users['manager'],
            category:
                'production-event-escalation',
            title:
                'Critical production event escalation',
            actionUrl: '/dashboard'
        );

        $this->assertNotification(
            user: $users['administrator'],
            category:
                'production-event-escalation',
            title:
                'Critical production event escalation',
            actionUrl:
                route(
                    'production.supervisor.events.show',
                    $event,
                    false
                )
        );

        $this->assertNotificationCount(
            0,
            $users['operator']
        );
    }

    public function test_operator_comment_notifies_production_supervisor_only(): void
    {
        $users =
            $this->roleUsers();

        $this->createEvent(
            operator: $users['operator'],
            type:
                ProductionEventType::Comment,
            severity:
                ProductionEventSeverity
                    ::Information,
        );

        $this->assertNotification(
            user: $users['supervisor'],
            category: 'production-event',
            title:
                'Operator production comment reported'
        );

        $this->assertNotificationCount(
            0,
            $users['maintenance']
        );

        $this->assertNotificationCount(
            0,
            $users['manager']
        );

        $this->assertNotificationCount(
            0,
            $users['administrator']
        );

        $this->assertNotificationCount(
            0,
            $users['operator']
        );
    }

    public function test_reporting_operator_is_notified_when_event_is_resolved(): void
    {
        $users =
            $this->roleUsers();

        $event =
            $this->createEvent(
                operator: $users['operator'],
                type:
                    ProductionEventType
                        ::Downtime,
                severity:
                    ProductionEventSeverity
                        ::Warning,
            );

        $this->assertNotificationCount(
            0,
            $users['operator']
        );

        $event->forceFill([
            'ended_at' =>
                CarbonImmutable::parse(
                    '2026-07-15 10:20:00'
                ),

            'duration_minutes' => 20,

            'is_resolved' => true,

            'resolved_at' =>
                CarbonImmutable::parse(
                    '2026-07-15 10:20:00'
                ),

            'resolved_by' =>
                $users['supervisor']
                    ->getKey(),

            'lock_version' => 2,
        ])->save();

        $this->assertNotification(
            user: $users['operator'],
            category:
                'production-event-resolution',
            title:
                'Reported production event resolved',
            actionUrl:
                route(
                    'production.operator.events.show',
                    $event,
                    false
                )
        );
    }

    /**
     * @return array{
     *     operator: User,
     *     supervisor: User,
     *     maintenance: User,
     *     manager: User,
     *     administrator: User
     * }
     */
    private function roleUsers(): array
    {
        return [
            'operator' =>
                $this->userWithRole(
                    RoleName::Operator
                ),

            'supervisor' =>
                $this->userWithRole(
                    RoleName
                        ::ProductionSupervisor
                ),

            'maintenance' =>
                $this->userWithRole(
                    RoleName
                        ::MaintenanceManager
                ),

            'manager' =>
                $this->userWithRole(
                    RoleName
                        ::ProductionManager
                ),

            'administrator' =>
                $this->userWithRole(
                    RoleName::Administrator
                ),
        ];
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

    private function createEvent(
        User $operator,
        ProductionEventType $type,
        ProductionEventSeverity $severity
    ): ProductionEvent {
        $assignment =
            OperatorAssignment::query()
                ->with([
                    'operator',
                    'productionLine',
                    'shift',
                ])
                ->orderBy('id')
                ->firstOrFail();

        $assignment->operator
            ->forceFill([
                'user_id' =>
                    $operator->getKey(),
            ])
            ->save();

        $unique =
            Str::upper(
                Str::random(10)
            );

        $order =
            $this->createOrder(
                $assignment,
                $unique
            );

        $batch =
            $this->createBatch(
                $order,
                $unique
            );

        $machine =
            $type
            === ProductionEventType
                ::MachineIncident
                ? Machine::query()
                    ->where(
                        'production_line_id',
                        $assignment
                            ->production_line_id
                    )
                    ->firstOrFail()
                : null;

        $event =
            new ProductionEvent();

        $event->fill([
            'event_number' =>
                'EVT-NOTIFY-'.$unique,

            'production_batch_id' =>
                $batch->getKey(),

            'production_record_id' =>
                null,

            'production_line_id' =>
                $assignment
                    ->production_line_id,

            'machine_id' =>
                $machine?->getKey(),

            'shift_id' =>
                $assignment->shift_id,

            'operator_id' =>
                $assignment->operator_id,

            'event_type' =>
                $type,

            'severity' =>
                $severity,

            'title' =>
                $type->label()
                .' notification test',

            'description' =>
                'Grounded production-event notification test.',

            'started_at' =>
                CarbonImmutable::parse(
                    '2026-07-15 10:00:00'
                ),

            'ended_at' => null,

            'duration_minutes' => null,
        ]);

        $event->forceFill([
            'reported_by' =>
                $operator->getKey(),

            'is_resolved' => false,

            'resolved_at' => null,

            'resolved_by' => null,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return $event->refresh();
    }

    private function createOrder(
        OperatorAssignment $assignment,
        string $unique
    ): ProductionOrder {
        $order =
            new ProductionOrder();

        $order->fill([
            'order_number' =>
                'PO-NOTIFY-'.$unique,

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

        return $order->refresh();
    }

    private function createBatch(
        ProductionOrder $order,
        string $unique
    ): ProductionBatch {
        $batch =
            new ProductionBatch();

        $batch->fill([
            'production_order_id' =>
                $order->getKey(),

            'batch_number' =>
                'BAT-NOTIFY-'.$unique,

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

        return $batch->refresh();
    }

    private function assertNotification(
        User $user,
        string $category,
        string $title,
        ?string $actionUrl = null
    ): void {
        $notification =
            $user
                ->fresh()
                ->notifications()
                ->get()
                ->first(
                    static fn (
                        DatabaseNotification $notification
                    ): bool =>
                        (
                            $notification
                                ->data['category']
                            ?? null
                        ) === $category
                        && (
                            $notification
                                ->data['title']
                            ?? null
                        ) === $title
                );

        $this->assertNotNull(
            $notification,
            "Expected notification [{$category}] [{$title}] was not delivered."
        );

        if ($actionUrl !== null) {
            $this->assertSame(
                $actionUrl,
                $notification
                    ->data['action_url']
            );
        }
    }

    private function assertNotificationCount(
        int $expected,
        User $user
    ): void {
        $this->assertSame(
            $expected,
            $user
                ->fresh()
                ->notifications()
                ->count()
        );
    }
}
