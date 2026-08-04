<?php

namespace Tests\Feature\Production;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterDataSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_master_data_tables_and_columns_exist(): void
    {
        $expectedTables = [
            'product_families',
            'products',
            'production_lines',
            'machines',
            'shifts',
            'operators',
            'operator_assignments',
        ];

        foreach ($expectedTables as $table) {
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
                        'created_at',
                        'updated_at',
                    ]
                ),
                "Missing synchronization columns on [{$table}]."
            );
        }

        $this->assertTrue(
            Schema::hasColumns(
                'products',
                [
                    'product_family_id',
                    'code',
                    'sku',
                    'name',
                    'is_active',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'machines',
                [
                    'production_line_id',
                    'code',
                    'machine_type',
                    'sequence_number',
                    'is_critical',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'operator_assignments',
                [
                    'operator_id',
                    'production_line_id',
                    'shift_id',
                    'assigned_by',
                    'starts_on',
                    'ends_on',
                    'is_primary',
                    'is_active',
                ]
            )
        );
    }

    public function test_external_identifier_is_unique_inside_one_source(): void
    {
        $now = now();

        DB::table('product_families')->insert([
            'code' => 'VAL-PREMIUM',
            'name' => 'Valencia Premium',
            'is_active' => true,
            'source_system' => 'simulated_sage',
            'external_id' => 'PF-001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('product_families')->insert([
            'code' => 'VAL-PREMIUM-COPY',
            'name' => 'Duplicate source record',
            'is_active' => true,
            'source_system' => 'simulated_sage',
            'external_id' => 'PF-001',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_same_external_identifier_can_exist_in_different_sources(): void
    {
        $now = now();

        DB::table('product_families')->insert([
            [
                'code' => 'VAL-PREMIUM-ERP',
                'name' => 'Valencia Premium ERP',
                'is_active' => true,
                'source_system' => 'simulated_sage',
                'external_id' => 'PF-001',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'VAL-PREMIUM-CSV',
                'name' => 'Valencia Premium CSV',
                'is_active' => true,
                'source_system' => 'csv',
                'external_id' => 'PF-001',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->assertSame(
            2,
            DB::table('product_families')
                ->where('external_id', 'PF-001')
                ->count()
        );
    }

    public function test_operator_assignment_can_reference_valid_master_data(): void
    {
        $now = now();

        $user = User::factory()->create();

        $lineId = DB::table(
            'production_lines'
        )->insertGetId([
            'code' => 'LINE-01',
            'name' => 'Pasteurization and Filling Line 01',
            'is_active' => true,
            'source_system' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $shiftId = DB::table(
            'shifts'
        )->insertGetId([
            'code' => 'SHIFT-A',
            'name' => 'Morning shift',
            'starts_at' => '06:00:00',
            'ends_at' => '14:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
            'source_system' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $operatorId = DB::table(
            'operators'
        )->insertGetId([
            'user_id' => $user->getKey(),
            'employee_code' => 'OP-001',
            'first_name' => 'Test',
            'last_name' => 'Operator',
            'is_active' => true,
            'source_system' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table(
            'operator_assignments'
        )->insert([
            'operator_id' => $operatorId,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'assigned_by' => $user->getKey(),
            'starts_on' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
            'source_system' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertDatabaseHas(
            'operator_assignments',
            [
                'operator_id' => $operatorId,
                'production_line_id' => $lineId,
                'shift_id' => $shiftId,
                'is_primary' => true,
                'is_active' => true,
            ]
        );
    }

    public function test_product_family_with_products_cannot_be_deleted(): void
    {
        $now = now();

        $familyId = DB::table(
            'product_families'
        )->insertGetId([
            'code' => 'VAL-ESSENTIAL',
            'name' => 'Valencia Essential',
            'is_active' => true,
            'source_system' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('products')->insert([
            'product_family_id' => $familyId,
            'code' => 'ORANGE-NECTAR-1L',
            'name' => 'Orange Nectar 1 L',
            'is_active' => true,
            'source_system' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(
            QueryException::class
        );

        DB::table('product_families')
            ->where('id', $familyId)
            ->delete();
    }
}