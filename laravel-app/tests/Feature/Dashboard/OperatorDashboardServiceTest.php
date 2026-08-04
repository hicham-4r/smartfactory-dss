<?php

namespace Tests\Feature\Dashboard;

use App\DTOs\Dashboard\DashboardFilter;
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
use App\Services\Dashboard\OperatorDashboardService;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\ProductionWorkflowPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-07-15 10:00:00',
                'Africa/Casablanca'
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

    public function test_unlinked_account_receives_safe_empty_snapshot(): void
    {
        $snapshot = app(
            OperatorDashboardService::class
        )->build(
            $this->userWithRole(
                RoleName::Operator
            ),
            $this->filter()
        );

        $this->assertFalse(
            $snapshot->profileLinked
        );

        $this->assertFalse(
            $snapshot->operatorActive
        );

        $this->assertSame(
            [],
            $snapshot->assignedOrders
        );

        $this->assertSame(
            0,
            $snapshot->recordCount
        );
    }

    public function test_dashboard_contains_only_authenticated_operator_data(): void
    {
        $own = $this->operatorContext(0);
        $other = $this->operatorContext(3);

        $ownFlow = $this->createFlow(
            $own['assignment'],
            'OWN'
        );

        $otherFlow = $this->createFlow(
            $other['assignment'],
            'OTHER'
        );

        $this->createRecord(
            user: $own['user'],
            assignment: $own['assignment'],
            batch: $ownFlow['batch'],
            suffix: 'OWN',
            producedQuantity: '1000.000',
        );

        $this->createRecord(
            user: $other['user'],
            assignment: $other['assignment'],
            batch: $otherFlow['batch'],
            suffix: 'OTHER',
            producedQuantity: '9000.000',
        );

        $this->createEvent(
            user: $own['user'],
            assignment: $own['assignment'],
            batch: $ownFlow['batch'],
            suffix: 'OWN',
        );

        $this->createEvent(
            user: $other['user'],
            assignment: $other['assignment'],
            batch: $otherFlow['batch'],
            suffix: 'OTHER',
        );

        $snapshot = app(
            OperatorDashboardService::class
        )->build(
            $own['user'],
            $this->filter()
        );

        $this->assertTrue(
            $snapshot->profileLinked
        );

        $this->assertTrue(
            $snapshot->operatorActive
        );

        $this->assertNotEmpty(
            $snapshot->assignments
        );

        $this->assertSame(
            1,
            $snapshot->recordCount
        );

        $this->assertSame(
            1,
            $snapshot->reportedDowntimeCount
        );

        $this->assertSame(
            ['PO-DASH-OWN'],
            array_map(
                static fn (
                    $item
                ): string => $item->orderNumber,
                $snapshot->assignedOrders
            )
        );

        $this->assertSame(
            ['PR-DASH-OWN'],
            array_map(
                static fn (
                    $item
                ): string => $item->recordNumber,
                $snapshot->recentRecords
            )
        );

        $this->assertSame(
            ['PE-DASH-OWN'],
            array_map(
                static fn (
                    $item
                ): string => $item->eventNumber,
                $snapshot->recentEvents
            )
        );

        $this->assertSame(
            '1000.000',
            $snapshot
                ->quantityUnits[0]
                ->producedQuantity
        );
    }

    /**
     * @return array{
     *     user: User,
     *     assignment: OperatorAssignment
     * }
     */
    private function operatorContext(
        int $offset
    ): array {
        $assignment = OperatorAssignment::query()
            ->with([
                'operator',
                'productionLine',
                'shift',
            ])
            ->orderBy('id')
            ->skip($offset)
            ->firstOrFail();

        $user = $this->userWithRole(
            RoleName::Operator
        );

        $assignment->operator
            ->forceFill([
                'user_id' => $user->getKey(),
            ])
            ->save();

        return [
            'user' => $user,
            'assignment' => $assignment->refresh(),
        ];
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
        $order = new ProductionOrder();

        $order->fill([
            'order_number' =>
                'PO-DASH-'.$suffix,

            'product_id' =>
                Product::query()
                    ->firstOrFail()
                    ->getKey(),

            'production_line_id' =>
                $assignment->production_line_id,

            'shift_id' =>
                $assignment->shift_id,

            'planned_start_at' =>
                '2026-07-15 06:00:00',

            'planned_end_at' =>
                '2026-07-15 14:00:00',

            'target_quantity' =>
                '1000.000',

            'quantity_unit' => 'bottles',

            'priority' => 2,
        ]);

        $order->forceFill([
            'status' =>
                ProductionOrderStatus::InProgress,

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
                'BAT-DASH-'.$suffix,

            'sequence_number' => 1,

            'planned_quantity' =>
                '1000.000',

            'actual_good_quantity' =>
                '0.000',

            'actual_rejected_quantity' =>
                '0.000',

            'quantity_unit' => 'bottles',

            'scheduled_start_at' =>
                '2026-07-15 06:00:00',

            'actual_start_at' =>
                '2026-07-15 06:00:00',
        ]);

        $batch->forceFill([
            'status' =>
                ProductionBatchStatus::InProgress,

            'lock_version' => 1,

            'source_system' => 'manual',

            'import_status' =>
                ProductionImportStatus::NotApplicable,
        ])->save();

        return [
            'order' => $order->refresh(),
            'batch' => $batch->refresh(),
        ];
    }

    private function createRecord(
        User $user,
        OperatorAssignment $assignment,
        ProductionBatch $batch,
        string $suffix,
        string $producedQuantity,
    ): ProductionRecord {
        $record = new ProductionRecord();

        $record->fill([
            'record_number' =>
                'PR-DASH-'.$suffix,

            'production_batch_id' =>
                $batch->getKey(),

            'production_line_id' =>
                $assignment->production_line_id,

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
                $producedQuantity,

            'good_quantity' =>
                $producedQuantity,

            'rejected_quantity' =>
                '0.000',

            'quantity_unit' => 'bottles',

            'runtime_minutes' => 450,

            'downtime_minutes' => 30,
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

        return $record->refresh();
    }

    private function createEvent(
        User $user,
        OperatorAssignment $assignment,
        ProductionBatch $batch,
        string $suffix,
    ): ProductionEvent {
        $event = new ProductionEvent();

        $event->fill([
            'event_number' =>
                'PE-DASH-'.$suffix,

            'production_batch_id' =>
                $batch->getKey(),

            'production_line_id' =>
                $assignment->production_line_id,

            'shift_id' =>
                $assignment->shift_id,

            'operator_id' =>
                $assignment->operator_id,

            'event_type' =>
                ProductionEventType::Downtime,

            'severity' =>
                ProductionEventSeverity::Warning,

            'title' =>
                'Dashboard event '.$suffix,

            'started_at' =>
                '2026-07-15 10:00:00',

            'ended_at' =>
                '2026-07-15 10:15:00',

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

        return $event->refresh();
    }

    private function filter(): DashboardFilter
    {
        return new DashboardFilter(
            startDate: CarbonImmutable::parse(
                '2026-07-01',
                'Africa/Casablanca'
            ),
            endDate: CarbonImmutable::parse(
                '2026-07-31',
                'Africa/Casablanca'
            ),
            timezone: 'Africa/Casablanca',
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
}
