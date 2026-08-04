<?php

namespace Tests\Feature\Production;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Repositories\Contracts\ProductionMasterDataRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_family_and_product_relationships_work(): void
    {
        $family = ProductFamily::create([
            'code' => 'VAL-PREMIUM',
            'name' => 'Valencia Premium',
            'is_active' => true,
        ]);

        $product = Product::create([
            'product_family_id' => $family->getKey(),
            'code' => 'ORANGE-PREMIUM-1L',
            'sku' => 'VP-OR-1000',
            'name' => 'Valencia Premium Orange 1 L',
            'base_unit' => 'bottle',
            'package_format' => '1 L',
            'nominal_volume' => 1,
            'is_active' => true,
        ]);

        $product->refresh();

        $this->assertTrue(
            $product->productFamily->is($family)
        );

        $this->assertTrue(
            $family
                ->products()
                ->whereKey($product->getKey())
                ->exists()
        );

        $this->assertSame(
            '1.000',
            $product->nominal_volume
        );

        $this->assertTrue(
            $product->is_active
        );
    }

    public function test_production_line_and_machine_relationships_work(): void
    {
        $line = ProductionLine::create([
            'code' => 'LINE-01',
            'name' => 'Pasteurization and Filling Line 01',
            'nominal_capacity_per_hour' => 12000,
            'capacity_unit' => 'bottles',
            'is_active' => true,
        ]);

        $machine = Machine::create([
            'production_line_id' => $line->getKey(),
            'code' => 'MIX-01',
            'name' => 'Mixing Machine 01',
            'machine_type' => 'mixing',
            'sequence_number' => 2,
            'is_critical' => true,
            'is_active' => true,
        ]);

        $this->assertTrue(
            $machine->productionLine->is($line)
        );

        $this->assertTrue(
            $line
                ->activeMachines()
                ->whereKey($machine->getKey())
                ->exists()
        );

        $this->assertTrue(
            $machine->is_critical
        );
    }

    public function test_operator_assignment_relationships_work(): void
    {
        $administrator = User::factory()->create();

        $operator = Operator::create([
            'employee_code' => 'OP-001',
            'first_name' => 'Test',
            'last_name' => 'Operator',
            'is_active' => true,
        ]);

        $operator->forceFill([
            'user_id' => $administrator->getKey(),
        ])->save();

        $line = ProductionLine::create([
            'code' => 'LINE-02',
            'name' => 'Packaging Line 02',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'code' => 'SHIFT-A',
            'name' => 'Morning shift',
            'starts_at' => '06:00:00',
            'ends_at' => '14:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
        ]);

        $assignment = OperatorAssignment::create([
            'operator_id' => $operator->getKey(),
            'production_line_id' => $line->getKey(),
            'shift_id' => $shift->getKey(),
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => null,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $assignment->forceFill([
            'assigned_by' => $administrator->getKey(),
        ])->save();

        $assignment->refresh();

        $this->assertTrue(
            $assignment->operator->is($operator)
        );

        $this->assertTrue(
            $assignment->productionLine->is($line)
        );

        $this->assertTrue(
            $assignment->shift->is($shift)
        );

        $this->assertTrue(
            $assignment->assignedBy->is($administrator)
        );

        $this->assertSame(
            'Test Operator',
            $operator->full_name
        );
    }

    public function test_active_and_source_scopes_work(): void
    {
        ProductFamily::create([
            'code' => 'ACTIVE-FAMILY',
            'name' => 'Active family',
            'is_active' => true,
        ]);

        ProductFamily::create([
            'code' => 'INACTIVE-FAMILY',
            'name' => 'Inactive family',
            'is_active' => false,
        ]);

        $externalFamily = new ProductFamily();

        $externalFamily->fill([
            'code' => 'ERP-FAMILY',
            'name' => 'ERP family',
            'is_active' => true,
        ]);

        $externalFamily->forceFill([
            'source_system' => 'simulated_sage',
            'external_id' => 'PF-001',
            'source_version' => 3,
            'last_synced_at' => now(),
        ])->save();

        $this->assertSame(
            2,
            ProductFamily::query()
                ->active()
                ->count()
        );

        $this->assertSame(
            1,
            ProductFamily::query()
                ->fromSource('simulated_sage')
                ->count()
        );

        $this->assertSame(
            1,
            ProductFamily::query()
                ->externallyManaged()
                ->count()
        );

        $this->assertTrue(
            $externalFamily->isExternallyManaged()
        );

        $this->assertSame(
            'simulated_sage:PF-001',
            $externalFamily->sourceIdentity()
        );
    }

    public function test_source_fields_are_not_mass_assignable(): void
    {
        $model = new ProductFamily();

        $this->assertNotContains(
            'source_system',
            $model->getFillable()
        );

        $this->assertNotContains(
            'external_id',
            $model->getFillable()
        );

        $this->assertNotContains(
            'source_checksum',
            $model->getFillable()
        );

        $this->assertNotContains(
            'last_synced_at',
            $model->getFillable()
        );
    }

    public function test_current_assignment_scope_excludes_expired_assignments(): void
    {
        $operator = Operator::create([
            'employee_code' => 'OP-002',
            'first_name' => 'Current',
            'last_name' => 'Operator',
            'is_active' => true,
        ]);

        $line = ProductionLine::create([
            'code' => 'LINE-03',
            'name' => 'Filling Line 03',
            'is_active' => true,
        ]);

        $shift = Shift::create([
            'code' => 'SHIFT-B',
            'name' => 'Afternoon shift',
            'starts_at' => '14:00:00',
            'ends_at' => '22:00:00',
            'crosses_midnight' => false,
            'is_active' => true,
        ]);

        $current = OperatorAssignment::create([
            'operator_id' => $operator->getKey(),
            'production_line_id' => $line->getKey(),
            'shift_id' => $shift->getKey(),
            'starts_on' => '2026-07-01',
            'ends_on' => null,
            'is_primary' => true,
            'is_active' => true,
        ]);

        OperatorAssignment::create([
            'operator_id' => $operator->getKey(),
            'production_line_id' => $line->getKey(),
            'shift_id' => $shift->getKey(),
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-06-30',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $date = CarbonImmutable::parse(
            '2026-07-15'
        );

        $assignments = OperatorAssignment::query()
            ->current($date)
            ->get();

        $this->assertCount(
            1,
            $assignments
        );

        $this->assertTrue(
            $assignments->first()->is($current)
        );

        $this->assertTrue(
            $current->isEffectiveOn($date)
        );
    }

    public function test_repository_returns_only_active_master_data(): void
    {
        $activeFamily = ProductFamily::create([
            'code' => 'REPOSITORY-ACTIVE',
            'name' => 'Repository active family',
            'is_active' => true,
        ]);

        $inactiveFamily = ProductFamily::create([
            'code' => 'REPOSITORY-INACTIVE',
            'name' => 'Repository inactive family',
            'is_active' => false,
        ]);

        Product::create([
            'product_family_id' =>
                $activeFamily->getKey(),

            'code' => 'ACTIVE-PRODUCT',
            'name' => 'Active product',
            'is_active' => true,
        ]);

        Product::create([
            'product_family_id' =>
                $inactiveFamily->getKey(),

            'code' => 'INACTIVE-PRODUCT',
            'name' => 'Inactive product',
            'is_active' => false,
        ]);

        $repository = app(
            ProductionMasterDataRepositoryInterface::class
        );

        $families = $repository
            ->activeProductFamilies();

        $products = $repository
            ->activeProducts();

        $this->assertCount(
            1,
            $families
        );

        $this->assertTrue(
            $families->first()->is($activeFamily)
        );

        $this->assertTrue(
            $families
                ->first()
                ->relationLoaded('products')
        );

        $this->assertCount(
            1,
            $products
        );

        $this->assertSame(
            'ACTIVE-PRODUCT',
            $products->first()->code
        );
    }
}