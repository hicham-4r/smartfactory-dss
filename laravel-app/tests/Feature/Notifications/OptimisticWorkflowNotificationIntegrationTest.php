<?php

namespace Tests\Feature\Notifications;

use App\Enums\Production\ProductionBatchStatus;
use App\Enums\Production\ProductionEventSeverity;
use App\Enums\Production\ProductionEventType;
use App\Enums\Production\ProductionImportStatus;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Production\ProductionRecordStatus;
use App\Enums\Production\ProductionValidationStatus;
use App\Enums\RoleName;
use App\Exceptions\Production\OptimisticLockException;
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

final class OptimisticWorkflowNotificationIntegrationTest
    extends TestCase
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

    public function test_optimistic_record_submission_dispatches_notification(): void
    {
        $operator =
            $this->userWithRole(
                RoleName::Operator
            );

        $supervisor =
            $this->userWithRole(
                RoleName::ProductionSupervisor
            );

        $record =
            $this->createDraftRecord(
                $operator
            );

        $updated =
            $record->updateWithOptimisticLock(
                [
                    'status' =>
                        ProductionRecordStatus
                            ::Submitted,

                    'submitted_at' =>
                        now(),

                    'updated_by' =>
                        $operator->getKey(),
                ],
                1
            );

        $this->assertSame(
            ProductionRecordStatus::Submitted,
            $updated->status
        );

        $this->assertSame(
            2,
            $updated->lock_version
        );

        $this->assertDatabaseHas(
            'notifications',
            [
                'notifiable_type' =>
                    $supervisor
                        ->getMorphClass(),

                'notifiable_id' =>
                    $supervisor->getKey(),

                'category' =>
                    'production-record',
            ]
        );
    }

    public function test_optimistic_record_rejection_notifies_operator(): void
    {
        $operator =
            $this->userWithRole(
                RoleName::Operator
            );

        $supervisor =
            $this->userWithRole(
                RoleName::ProductionSupervisor
            );

        $record =
            $this->createDraftRecord(
                $operator
            );

        $submitted =
            $record->updateWithOptimisticLock(
                [
                    'status' =>
                        ProductionRecordStatus
                            ::Submitted,

                    'submitted_at' =>
                        now(),

                    'updated_by' =>
                        $operator->getKey(),
                ],
                1
            );

        $operator->notifications()->delete();

        $rejected =
            $submitted->updateWithOptimisticLock(
                [
                    'status' =>
                        ProductionRecordStatus::Draft,

                    'validation_status' =>
                        ProductionValidationStatus
                            ::Rejected,

                    'updated_by' =>
                        $supervisor->getKey(),
                ],
                2
            );

        $this->assertSame(
            ProductionValidationStatus::Rejected,
            $rejected->validation_status
        );

        $this->assertDatabaseHas(
            'notifications',
            [
                'notifiable_type' =>
                    $operator
                        ->getMorphClass(),

                'notifiable_id' =>
                    $operator->getKey(),

                'category' =>
                    'production-record',
            ]
        );
    }

    public function test_optimistic_event_resolution_notifies_operator(): void
    {
        $operator =
            $this->userWithRole(
                RoleName::Operator
            );

        $supervisor =
            $this->userWithRole(
                RoleName::ProductionSupervisor
            );

        $event =
            $this->createOpenEvent(
                $operator
            );

        $operator->notifications()->delete();

        $resolved =
            $event->updateWithOptimisticLock(
                [
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
                        $supervisor->getKey(),
                ],
                1
            );

        $this->assertTrue(
            $resolved->is_resolved
        );

        $this->assertDatabaseHas(
            'notifications',
            [
                'notifiable_type' =>
                    $operator
                        ->getMorphClass(),

                'notifiable_id' =>
                    $operator->getKey(),

                'category' =>
                    'production-event-resolution',
            ]
        );
    }

    public function test_stale_optimistic_version_remains_rejected(): void
    {
        $operator =
            $this->userWithRole(
                RoleName::Operator
            );

        $record =
            $this->createDraftRecord(
                $operator
            );

        $record->updateWithOptimisticLock(
            [
                'notes' =>
                    'First atomic update',
            ],
            1
        );

        $this->expectException(
            OptimisticLockException::class
        );

        $record->updateWithOptimisticLock(
            [
                'notes' =>
                    'Stale update must fail',
            ],
            1
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

    private function createDraftRecord(
        User $operatorUser
    ): ProductionRecord {
        [$assignment, $batch] =
            $this->productionContext(
                $operatorUser
            );

        $record =
            new ProductionRecord();

        $record->fill([
            'record_number' =>
                'PR-NOTIFY-'
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
                '2026-07-15 09:00:00',

            'ended_at' =>
                '2026-07-15 10:00:00',

            'produced_quantity' =>
                '100.000',

            'good_quantity' =>
                '95.000',

            'rejected_quantity' =>
                '5.000',

            'quantity_unit' =>
                'bottles',

            'runtime_minutes' => 55,

            'downtime_minutes' => 5,

            'notes' =>
                'Optimistic notification integration test.',
        ]);

        $record->forceFill([
            'recorded_by' =>
                $operatorUser->getKey(),

            'updated_by' =>
                $operatorUser->getKey(),

            'status' =>
                ProductionRecordStatus::Draft,

            'validation_status' =>
                ProductionValidationStatus
                    ::Pending,

            'submitted_at' => null,

            'locked_at' => null,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus
                    ::NotApplicable,
        ])->save();

        return $record->refresh();
    }

    private function createOpenEvent(
        User $operatorUser
    ): ProductionEvent {
        [$assignment, $batch] =
            $this->productionContext(
                $operatorUser
            );

        $event =
            new ProductionEvent();

        $event->fill([
            'event_number' =>
                'EVT-NOTIFY-'
                .Str::upper(
                    Str::random(10)
                ),

            'production_batch_id' =>
                $batch->getKey(),

            'production_record_id' =>
                null,

            'production_line_id' =>
                $assignment
                    ->production_line_id,

            'machine_id' => null,

            'shift_id' =>
                $assignment->shift_id,

            'operator_id' =>
                $assignment->operator_id,

            'event_type' =>
                ProductionEventType::Downtime,

            'severity' =>
                ProductionEventSeverity::Warning,

            'title' =>
                'Optimistic resolution test',

            'description' =>
                'Test event resolution notification.',

            'started_at' =>
                '2026-07-15 10:00:00',

            'ended_at' => null,

            'duration_minutes' => null,
        ]);

        $event->forceFill([
            'reported_by' =>
                $operatorUser->getKey(),

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

    /**
     * @return array{0: OperatorAssignment, 1: ProductionBatch}
     */
    private function productionContext(
        User $operatorUser
    ): array {
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
                    $operatorUser->getKey(),
            ])
            ->save();

        $unique =
            Str::upper(
                Str::random(10)
            );

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

        return [
            $assignment,
            $batch->refresh(),
        ];
    }
}
