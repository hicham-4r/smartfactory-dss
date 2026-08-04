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
use App\Exceptions\Production\InvalidProductionStatusTransition;
use App\Exceptions\Production\OptimisticLockException;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionEvent;
use App\Models\ProductionLine;
use App\Models\ProductionOrder;
use App\Models\ProductionRecord;
use App\Models\ProductionRecordValidation;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\ProductionExecutionRepositoryInterface;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionExecutionModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_production_status_transitions_are_controlled(): void
    {
        $this->assertTrue(
            ProductionOrderStatus::Draft
                ->canTransitionTo(
                    ProductionOrderStatus::Planned
                )
        );

        $this->assertFalse(
            ProductionOrderStatus::Completed
                ->canTransitionTo(
                    ProductionOrderStatus::InProgress
                )
        );

        $this->assertTrue(
            ProductionBatchStatus::Blocked
                ->canTransitionTo(
                    ProductionBatchStatus::Ready
                )
        );

        $this->assertFalse(
            ProductionRecordStatus::Locked
                ->canTransitionTo(
                    ProductionRecordStatus::Draft
                )
        );

        $this->assertTrue(
            ProductionValidationStatus::Rejected
                ->canTransitionTo(
                    ProductionValidationStatus::Pending
                )
        );
    }

    public function test_models_cast_enums_and_load_relationships(): void
    {
        $flow = $this->createExecutionFlow();

        $order = $flow['order']->refresh();
        $batch = $flow['batch']->refresh();
        $record = $flow['record']->refresh();
        $event = $flow['event']->refresh();

        $this->assertSame(
            ProductionOrderStatus::Released,
            $order->status
        );

        $this->assertSame(
            ProductionBatchStatus::InProgress,
            $batch->status
        );

        $this->assertSame(
            ProductionRecordStatus::Submitted,
            $record->status
        );

        $this->assertSame(
            ProductionValidationStatus::Pending,
            $record->validation_status
        );

        $this->assertSame(
            ProductionEventType::Downtime,
            $event->event_type
        );

        $this->assertSame(
            ProductionEventSeverity::Warning,
            $event->severity
        );

        $this->assertTrue(
            $order->product->is(
                $flow['product']
            )
        );

        $this->assertTrue(
            $batch->productionOrder->is(
                $order
            )
        );

        $this->assertTrue(
            $record->productionBatch->is(
                $batch
            )
        );

        $this->assertTrue(
            $event->productionRecord->is(
                $record
            )
        );

        $this->assertTrue(
            $event->machine->is(
                $flow['machine']
            )
        );
    }

    public function test_production_quantity_helpers_detect_consistency(): void
    {
        $flow = $this->createExecutionFlow();

        $record = $flow['record'];

        $this->assertTrue(
            $record
                ->hasConsistentQuantityBreakdown()
        );

        $this->assertTrue(
            $record
                ->hasNonNegativeOperationalValues()
        );

        $this->assertFalse(
            $record->canBeSubmitted()
        );

        $record->forceFill([
            'status' =>
                ProductionRecordStatus::Draft,
        ]);

        $this->assertTrue(
            $record->canBeSubmitted()
        );

        $batch = $flow['batch'];

        $this->assertSame(
            '1000.000',
            $batch->actualTotalQuantity()
        );

        $this->assertSame(
            '0.000',
            $batch->remainingPlannedQuantity()
        );
    }

    public function test_repository_updates_record_with_optimistic_lock(): void
    {
        $flow = $this->createExecutionFlow();

        $repository = app(
            ProductionExecutionRepositoryInterface::class
        );

        $order = $flow['order'];

        $updated = $repository->updateOrder(
            order: $order,
            changes: [
                'status' =>
                    ProductionOrderStatus::InProgress,

                'instructions' =>
                    'Production was started after line verification.',

                'updated_by' =>
                    $flow['user']->getKey(),
            ],
            expectedVersion: 1
        );

        $this->assertSame(
            ProductionOrderStatus::InProgress,
            $updated->status
        );

        $this->assertSame(
            2,
            $updated->lock_version
        );

        $this->assertSame(
            'Production was started after line verification.',
            $updated->instructions
        );
    }

    public function test_stale_optimistic_update_is_rejected(): void
    {
        $flow = $this->createExecutionFlow();

        $repository = app(
            ProductionExecutionRepositoryInterface::class
        );

        $firstCopy = ProductionOrder::query()
            ->findOrFail(
                $flow['order']->getKey()
            );

        $staleCopy = ProductionOrder::query()
            ->findOrFail(
                $flow['order']->getKey()
            );

        $repository->updateOrder(
            order: $firstCopy,
            changes: [
                'instructions' =>
                    'First concurrent change.',
            ],
            expectedVersion: 1
        );

        $this->expectException(
            OptimisticLockException::class
        );

        $repository->updateOrder(
            order: $staleCopy,
            changes: [
                'instructions' =>
                    'Stale concurrent change.',
            ],
            expectedVersion: 1
        );
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $flow = $this->createExecutionFlow();

        $repository = app(
            ProductionExecutionRepositoryInterface::class
        );

        $this->expectException(
            InvalidProductionStatusTransition::class
        );

        $repository->updateOrder(
            order: $flow['order'],
            changes: [
                'status' =>
                    ProductionOrderStatus::Completed,
            ],
            expectedVersion: 1
        );
    }

    public function test_repository_rejects_disallowed_update_fields(): void
    {
        $flow = $this->createExecutionFlow();

        $repository = app(
            ProductionExecutionRepositoryInterface::class
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $repository->updateOrder(
            order: $flow['order'],
            changes: [
                'external_id' =>
                    'UNAUTHORIZED-CHANGE',
            ],
            expectedVersion: 1
        );
    }

    public function test_pending_validation_repository_query_returns_only_pending_submissions(): void
    {
        $flow = $this->createExecutionFlow();

        $repository = app(
            ProductionExecutionRepositoryInterface::class
        );

        $records = $repository
            ->pendingRecordsForValidation();

        $this->assertCount(
            1,
            $records
        );

        $this->assertTrue(
            $records->first()->is(
                $flow['record']
            )
        );

        $this->assertTrue(
            $records
                ->first()
                ->relationLoaded(
                    'productionBatch'
                )
        );
    }

    public function test_validation_history_and_import_metadata_are_cast_correctly(): void
    {
        $flow = $this->createExecutionFlow();

        $validation =
            new ProductionRecordValidation();

        $validation->forceFill([
            'production_record_id' =>
                $flow['record']->getKey(),

            'decided_by' =>
                $flow['user']->getKey(),

            'decision' =>
                ProductionValidationDecision::Validated,

            'record_version' => 1,

            'decision_reason' =>
                'Values verified in the production log.',

            'decided_at' => now(),

            'request_id' =>
                'test-validation-request',
        ])->save();

        $validation->refresh();

        $this->assertSame(
            ProductionValidationDecision::Validated,
            $validation->decision
        );

        $this->assertTrue(
            $validation->productionRecord->is(
                $flow['record']
            )
        );

        $this->assertTrue(
            $validation->decidedBy->is(
                $flow['user']
            )
        );

        $order = $flow['order'];

        $order->forceFill([
            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-PO-TEST',

            'import_status' =>
                ProductionImportStatus::Imported,

            'source_version' => 4,

            'last_synced_at' => now(),
        ])->save();

        $order->refresh();

        $this->assertSame(
            ProductionImportStatus::Imported,
            $order->import_status
        );

        $this->assertTrue(
            $order
                ->import_status
                ->isSuccessful()
        );

        $this->assertSame(
            'simulated_sage:ERP-PO-TEST',
            $order->sourceIdentity()
        );
    }

    /**
     * @return array{
     *     user: User,
     *     product: Product,
     *     line: ProductionLine,
     *     shift: Shift,
     *     operator: Operator,
     *     machine: Machine,
     *     order: ProductionOrder,
     *     batch: ProductionBatch,
     *     record: ProductionRecord,
     *     event: ProductionEvent
     * }
     */
    private function createExecutionFlow(): array
    {
        $user = User::factory()->create();

        $product = Product::query()
            ->firstOrFail();

        $line = ProductionLine::query()
            ->firstOrFail();

        $shift = Shift::query()
            ->firstOrFail();

        $operator = Operator::query()
            ->firstOrFail();

        $machine = Machine::query()
            ->where(
                'production_line_id',
                $line->getKey()
            )
            ->firstOrFail();

        $order = new ProductionOrder();

        $order->fill([
            'order_number' =>
                'PO-MODEL-0001',

            'product_id' =>
                $product->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'planned_start_at' =>
                '2026-07-30 06:00:00',

            'planned_end_at' =>
                '2026-07-30 14:00:00',

            'target_quantity' => 1000,
            'quantity_unit' => 'bottles',
            'priority' => 2,
        ]);

        $order->forceFill([
            'status' =>
                ProductionOrderStatus::Released,

            'created_by' =>
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus::NotApplicable,
        ])->save();

        $batch = new ProductionBatch();

        $batch->fill([
            'production_order_id' =>
                $order->getKey(),

            'batch_number' =>
                'BATCH-MODEL-0001',

            'sequence_number' => 1,

            'planned_quantity' => 1000,

            'actual_good_quantity' => 975,

            'actual_rejected_quantity' => 25,

            'quantity_unit' => 'bottles',

            'scheduled_start_at' =>
                '2026-07-30 06:00:00',

            'actual_start_at' =>
                '2026-07-30 06:10:00',
        ]);

        $batch->forceFill([
            'status' =>
                ProductionBatchStatus::InProgress,

            'created_by' =>
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus::NotApplicable,
        ])->save();

        $record = new ProductionRecord();

        $record->fill([
            'record_number' =>
                'PR-MODEL-0001',

            'production_batch_id' =>
                $batch->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'operator_id' =>
                $operator->getKey(),

            'production_date' =>
                '2026-07-30',

            'started_at' =>
                '2026-07-30 06:10:00',

            'ended_at' =>
                '2026-07-30 13:50:00',

            'produced_quantity' => 1000,
            'good_quantity' => 975,
            'rejected_quantity' => 25,
            'quantity_unit' => 'bottles',
            'runtime_minutes' => 430,
            'downtime_minutes' => 30,
            'notes' => 'Synthetic model test.',
        ]);

        $record->forceFill([
            'recorded_by' =>
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'status' =>
                ProductionRecordStatus::Submitted,

            'validation_status' =>
                ProductionValidationStatus::Pending,

            'submitted_at' => now(),

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus::NotApplicable,
        ])->save();

        $event = new ProductionEvent();

        $event->fill([
            'event_number' =>
                'EVT-MODEL-0001',

            'production_batch_id' =>
                $batch->getKey(),

            'production_record_id' =>
                $record->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'machine_id' =>
                $machine->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'operator_id' =>
                $operator->getKey(),

            'event_type' =>
                ProductionEventType::Downtime,

            'severity' =>
                ProductionEventSeverity::Warning,

            'title' =>
                'Short synthetic interruption',

            'description' =>
                'Used for model relationship testing.',

            'started_at' =>
                '2026-07-30 10:00:00',

            'ended_at' =>
                '2026-07-30 10:15:00',

            'duration_minutes' => 15,
        ]);

        $event->forceFill([
            'reported_by' =>
                $user->getKey(),

            'is_resolved' => false,
            'lock_version' => 1,
            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus::NotApplicable,
        ])->save();

        return [
            'user' => $user,
            'product' => $product,
            'line' => $line,
            'shift' => $shift,
            'operator' => $operator,
            'machine' => $machine,
            'order' => $order,
            'batch' => $batch,
            'record' => $record,
            'event' => $event,
        ];
    }
}