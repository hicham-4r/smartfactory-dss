<?php

namespace Tests\Feature\Production;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionExecutionSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_all_production_execution_tables_and_columns_exist(): void
    {
        $sourceManagedTables = [
            'production_orders',
            'production_batches',
            'production_records',
            'production_events',
        ];

        foreach ($sourceManagedTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Missing expected table [{$table}]."
            );

            $this->assertTrue(
                Schema::hasColumns(
                    $table,
                    [
                        'id',
                        'source_system',
                        'external_id',
                        'source_version',
                        'source_checksum',
                        'source_updated_at',
                        'last_synced_at',
                        'import_status',
                        'import_error',
                        'created_at',
                        'updated_at',
                    ]
                ),
                "Missing synchronization columns on [{$table}]."
            );
        }

        $this->assertTrue(
            Schema::hasTable(
                'production_record_validations'
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'production_orders',
                [
                    'order_number',
                    'product_id',
                    'production_line_id',
                    'shift_id',
                    'target_quantity',
                    'status',
                    'priority',
                    'lock_version',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'production_batches',
                [
                    'production_order_id',
                    'batch_number',
                    'sequence_number',
                    'planned_quantity',
                    'actual_good_quantity',
                    'actual_rejected_quantity',
                    'status',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'production_records',
                [
                    'record_number',
                    'production_batch_id',
                    'production_line_id',
                    'shift_id',
                    'operator_id',
                    'recorded_by',
                    'produced_quantity',
                    'good_quantity',
                    'rejected_quantity',
                    'runtime_minutes',
                    'downtime_minutes',
                    'status',
                    'validation_status',
                    'lock_version',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'production_record_validations',
                [
                    'production_record_id',
                    'decided_by',
                    'decision',
                    'record_version',
                    'decision_reason',
                    'decided_at',
                    'request_id',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'production_events',
                [
                    'event_number',
                    'production_batch_id',
                    'production_record_id',
                    'production_line_id',
                    'machine_id',
                    'shift_id',
                    'operator_id',
                    'reported_by',
                    'event_type',
                    'severity',
                    'duration_minutes',
                    'is_resolved',
                    'resolved_by',
                    'lock_version',
                ]
            )
        );
    }

    public function test_complete_production_execution_flow_can_be_stored(): void
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

        $now = now();

        $orderId = DB::table(
            'production_orders'
        )->insertGetId([
            'order_number' =>
                'PO-TEST-0001',

            'product_id' =>
                $product->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'created_by' =>
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'planned_start_at' =>
                '2026-07-30 06:00:00',

            'planned_end_at' =>
                '2026-07-30 14:00:00',

            'target_quantity' =>
                10000,

            'quantity_unit' =>
                'bottles',

            'status' =>
                'released',

            'priority' => 2,

            'source_system' =>
                'manual',

            'import_status' =>
                'not_applicable',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $batchId = DB::table(
            'production_batches'
        )->insertGetId([
            'production_order_id' =>
                $orderId,

            'batch_number' =>
                'BATCH-TEST-0001',

            'sequence_number' => 1,

            'status' =>
                'in_progress',

            'planned_quantity' =>
                10000,

            'actual_good_quantity' =>
                9500,

            'actual_rejected_quantity' =>
                500,

            'quantity_unit' =>
                'bottles',

            'scheduled_start_at' =>
                '2026-07-30 06:00:00',

            'actual_start_at' =>
                '2026-07-30 06:10:00',

            'created_by' =>
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'source_system' =>
                'manual',

            'import_status' =>
                'not_applicable',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $recordId = DB::table(
            'production_records'
        )->insertGetId([
            'record_number' =>
                'PR-TEST-0001',

            'production_batch_id' =>
                $batchId,

            'production_line_id' =>
                $line->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'operator_id' =>
                $operator->getKey(),

            'recorded_by' =>
                $user->getKey(),

            'updated_by' =>
                $user->getKey(),

            'production_date' =>
                '2026-07-30',

            'started_at' =>
                '2026-07-30 06:10:00',

            'ended_at' =>
                '2026-07-30 13:50:00',

            'produced_quantity' =>
                10000,

            'good_quantity' =>
                9500,

            'rejected_quantity' =>
                500,

            'quantity_unit' =>
                'bottles',

            'runtime_minutes' => 430,
            'downtime_minutes' => 30,

            'status' =>
                'submitted',

            'validation_status' =>
                'validated',

            'submitted_at' =>
                '2026-07-30 14:00:00',

            'lock_version' => 1,

            'source_system' =>
                'manual',

            'import_status' =>
                'not_applicable',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table(
            'production_record_validations'
        )->insert([
            'production_record_id' =>
                $recordId,

            'decided_by' =>
                $user->getKey(),

            'decision' =>
                'validated',

            'record_version' => 1,

            'decision_reason' =>
                'Production values verified.',

            'decided_at' =>
                '2026-07-30 14:05:00',

            'request_id' =>
                'test-request-0001',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventId = DB::table(
            'production_events'
        )->insertGetId([
            'event_number' =>
                'EVT-TEST-0001',

            'production_batch_id' =>
                $batchId,

            'production_record_id' =>
                $recordId,

            'production_line_id' =>
                $line->getKey(),

            'machine_id' =>
                $machine->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'operator_id' =>
                $operator->getKey(),

            'reported_by' =>
                $user->getKey(),

            'event_type' =>
                'downtime',

            'severity' =>
                'warning',

            'title' =>
                'Temporary filling interruption',

            'description' =>
                'Synthetic test event.',

            'started_at' =>
                '2026-07-30 10:00:00',

            'ended_at' =>
                '2026-07-30 10:15:00',

            'duration_minutes' => 15,

            'is_resolved' => true,

            'resolved_at' =>
                '2026-07-30 10:15:00',

            'resolved_by' =>
                $user->getKey(),

            'source_system' =>
                'manual',

            'import_status' =>
                'not_applicable',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertDatabaseHas(
            'production_orders',
            [
                'id' => $orderId,
                'status' => 'released',
            ]
        );

        $this->assertDatabaseHas(
            'production_batches',
            [
                'id' => $batchId,
                'production_order_id' =>
                    $orderId,
            ]
        );

        $this->assertDatabaseHas(
            'production_records',
            [
                'id' => $recordId,
                'validation_status' =>
                    'validated',
            ]
        );

        $this->assertDatabaseHas(
            'production_record_validations',
            [
                'production_record_id' =>
                    $recordId,

                'decision' =>
                    'validated',

                'record_version' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'production_events',
            [
                'id' => $eventId,
                'event_type' => 'downtime',
                'machine_id' =>
                    $machine->getKey(),
            ]
        );
    }

    public function test_source_external_identifier_is_unique_inside_one_source(): void
    {
        $product = Product::query()
            ->firstOrFail();

        $line = ProductionLine::query()
            ->firstOrFail();

        $now = now();

        DB::table(
            'production_orders'
        )->insert([
            'order_number' =>
                'PO-ERP-0001',

            'product_id' =>
                $product->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'planned_start_at' =>
                '2026-07-30 06:00:00',

            'target_quantity' =>
                5000,

            'status' => 'planned',

            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-PO-001',

            'import_status' =>
                'imported',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'production_orders'
        )->insert([
            'order_number' =>
                'PO-ERP-0002',

            'product_id' =>
                $product->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'planned_start_at' =>
                '2026-07-31 06:00:00',

            'target_quantity' =>
                6000,

            'status' => 'planned',

            'source_system' =>
                'simulated_sage',

            'external_id' =>
                'ERP-PO-001',

            'import_status' =>
                'imported',

            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_order_with_a_batch_cannot_be_deleted(): void
    {
        $product = Product::query()
            ->firstOrFail();

        $line = ProductionLine::query()
            ->firstOrFail();

        $now = now();

        $orderId = DB::table(
            'production_orders'
        )->insertGetId([
            'order_number' =>
                'PO-DELETE-PROTECTION',

            'product_id' =>
                $product->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'planned_start_at' =>
                '2026-07-30 06:00:00',

            'target_quantity' =>
                3000,

            'status' => 'planned',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table(
            'production_batches'
        )->insert([
            'production_order_id' =>
                $orderId,

            'batch_number' =>
                'BATCH-DELETE-PROTECTION',

            'sequence_number' => 1,

            'planned_quantity' =>
                3000,

            'status' => 'planned',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('production_orders')
            ->where('id', $orderId)
            ->delete();
    }

    public function test_one_record_version_cannot_receive_duplicate_validation_decisions(): void
    {
        $user = User::factory()->create();

        $recordId = $this
            ->createProductionRecord(
                recordedBy: $user
            );

        $now = now();

        DB::table(
            'production_record_validations'
        )->insert([
            'production_record_id' =>
                $recordId,

            'decided_by' =>
                $user->getKey(),

            'decision' =>
                'validated',

            'record_version' => 1,

            'decided_at' => $now,

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table(
            'production_record_validations'
        )->insert([
            'production_record_id' =>
                $recordId,

            'decided_by' =>
                $user->getKey(),

            'decision' =>
                'rejected',

            'record_version' => 1,

            'decision_reason' =>
                'Duplicate decision test.',

            'decided_at' => $now,

            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createProductionRecord(
        User $recordedBy
    ): int {
        $product = Product::query()
            ->firstOrFail();

        $line = ProductionLine::query()
            ->firstOrFail();

        $shift = Shift::query()
            ->firstOrFail();

        $operator = Operator::query()
            ->firstOrFail();

        $now = now();

        $orderId = DB::table(
            'production_orders'
        )->insertGetId([
            'order_number' =>
                'PO-VALIDATION-TEST',

            'product_id' =>
                $product->getKey(),

            'production_line_id' =>
                $line->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'planned_start_at' =>
                '2026-07-30 06:00:00',

            'target_quantity' =>
                2000,

            'status' => 'released',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $batchId = DB::table(
            'production_batches'
        )->insertGetId([
            'production_order_id' =>
                $orderId,

            'batch_number' =>
                'BATCH-VALIDATION-TEST',

            'sequence_number' => 1,

            'planned_quantity' =>
                2000,

            'status' =>
                'in_progress',

            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table(
            'production_records'
        )->insertGetId([
            'record_number' =>
                'PR-VALIDATION-TEST',

            'production_batch_id' =>
                $batchId,

            'production_line_id' =>
                $line->getKey(),

            'shift_id' =>
                $shift->getKey(),

            'operator_id' =>
                $operator->getKey(),

            'recorded_by' =>
                $recordedBy->getKey(),

            'production_date' =>
                '2026-07-30',

            'produced_quantity' =>
                2000,

            'good_quantity' =>
                1950,

            'rejected_quantity' =>
                50,

            'status' =>
                'submitted',

            'validation_status' =>
                'pending',

            'submitted_at' => $now,

            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}