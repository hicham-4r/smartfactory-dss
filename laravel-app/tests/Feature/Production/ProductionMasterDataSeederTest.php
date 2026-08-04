<?php

namespace Tests\Feature\Production;

use App\Models\OperatorAssignment;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\User;
use Database\Seeders\ProductionMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionMasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_complete_simulated_catalogue(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $this->assertDatabaseCount(
            'product_families',
            5
        );

        $this->assertDatabaseCount(
            'products',
            16
        );

        $this->assertDatabaseCount(
            'production_lines',
            3
        );

        $this->assertDatabaseCount(
            'machines',
            21
        );

        $this->assertDatabaseCount(
            'shifts',
            3
        );

        $this->assertDatabaseCount(
            'operators',
            9
        );

        $this->assertDatabaseCount(
            'operator_assignments',
            9
        );
    }

    public function test_every_line_has_seven_ordered_machines(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $lines = ProductionLine::query()
            ->with([
                'machines' => fn ($query) =>
                    $query->orderBy(
                        'sequence_number'
                    ),
            ])
            ->get();

        $this->assertCount(
            3,
            $lines
        );

        foreach ($lines as $line) {
            $this->assertCount(
                7,
                $line->machines
            );

            $this->assertSame(
                [1, 2, 3, 4, 5, 6, 7],
                $line->machines
                    ->pluck('sequence_number')
                    ->all()
            );

            $this->assertGreaterThanOrEqual(
                6,
                $line->machines->count()
            );

            $this->assertLessThanOrEqual(
                8,
                $line->machines->count()
            );
        }
    }

    public function test_every_family_has_at_least_one_product(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $families = ProductFamily::query()
            ->withCount('products')
            ->get();

        $this->assertCount(
            5,
            $families
        );

        foreach ($families as $family) {
            $this->assertGreaterThan(
                0,
                $family->products_count,
                "Product family [{$family->code}] has no products."
            );
        }
    }

    public function test_source_metadata_is_complete(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $tables = [
            'product_families',
            'products',
            'production_lines',
            'machines',
            'shifts',
            'operators',
            'operator_assignments',
        ];

        foreach ($tables as $table) {
            $total = DB::table($table)
                ->count();

            $complete = DB::table($table)
                ->where(
                    'source_system',
                    'simulated_sage'
                )
                ->whereNotNull('external_id')
                ->whereNotNull('source_version')
                ->whereNotNull('source_checksum')
                ->whereRaw(
                    'LENGTH(source_checksum) = 64'
                )
                ->whereNotNull(
                    'source_updated_at'
                )
                ->whereNotNull(
                    'last_synced_at'
                )
                ->count();

            $this->assertSame(
                $total,
                $complete,
                "Incomplete source metadata exists in [{$table}]."
            );
        }
    }

    public function test_operator_assignments_are_valid_and_no_login_accounts_are_created(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        /*
         * Employee records remain separate from authentication users.
         */
        $this->assertSame(
            0,
            User::query()->count()
        );

        $assignments =
            OperatorAssignment::query()
                ->with([
                    'operator',
                    'productionLine',
                    'shift',
                    'assignedBy',
                ])
                ->get();

        $this->assertCount(
            9,
            $assignments
        );

        foreach ($assignments as $assignment) {
            $this->assertNotNull(
                $assignment->operator
            );

            $this->assertNotNull(
                $assignment->productionLine
            );

            $this->assertNotNull(
                $assignment->shift
            );

            $this->assertNull(
                $assignment->assignedBy
            );

            $this->assertNull(
                $assignment
                    ->operator
                    ->user_id
            );

            $this->assertTrue(
                $assignment->is_primary
            );

            $this->assertTrue(
                $assignment->is_active
            );
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $firstCounts =
            $this->tableCounts();

        $this->seed(
            ProductionMasterDataSeeder::class
        );

        $this->assertSame(
            $firstCounts,
            $this->tableCounts()
        );

        foreach (
            array_keys($firstCounts)
            as $table
        ) {
            $total = DB::table($table)
                ->count();

            $distinctExternalIds =
                DB::table($table)
                    ->distinct()
                    ->count('external_id');

            $this->assertSame(
                $total,
                $distinctExternalIds,
                "Duplicate external IDs exist in [{$table}]."
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return [
            'product_families' =>
                DB::table(
                    'product_families'
                )->count(),

            'products' =>
                DB::table(
                    'products'
                )->count(),

            'production_lines' =>
                DB::table(
                    'production_lines'
                )->count(),

            'machines' =>
                DB::table(
                    'machines'
                )->count(),

            'shifts' =>
                DB::table(
                    'shifts'
                )->count(),

            'operators' =>
                DB::table(
                    'operators'
                )->count(),

            'operator_assignments' =>
                DB::table(
                    'operator_assignments'
                )->count(),
        ];
    }
}