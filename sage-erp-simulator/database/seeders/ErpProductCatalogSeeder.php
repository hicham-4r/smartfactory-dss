<?php

namespace Database\Seeders;

use App\Models\ErpPackagingFormat;
use App\Models\ErpProcessStage;
use App\Models\ErpProduct;
use App\Models\ErpProductFamily;
use App\Models\ErpProductionLine;
use App\Models\ErpProductRoute;
use App\Models\ErpProductRouteStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ErpProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $families = ErpProductFamily::query()
            ->get()
            ->keyBy('code');

        $formats = ErpPackagingFormat::query()
            ->get()
            ->keyBy('code');

        $lines = ErpProductionLine::query()
            ->get()
            ->keyBy('code');

        $stages = ErpProcessStage::query()
            ->orderBy('sequence_order')
            ->get();

        $products = $this->productDefinitions();

        foreach ($products as $definition) {
            $family = $families->get($definition['family']);
            $format = $formats->get($definition['format']);
            $line = $lines->get($definition['line']);

            if (!$family || !$format || !$line) {
                throw new RuntimeException(
                    'Missing reference data for product: '
                    . $definition['code']
                );
            }

            $product = ErpProduct::query()->updateOrCreate(
                [
                    'code' => $definition['code'],
                ],
                [
                    'product_family_id' => $family->id,
                    'packaging_format_id' => $format->id,
                    'name' => $definition['name'],
                    'flavor' => $definition['flavor'],
                    'beverage_type' => $definition['beverage_type'],
                    'contains_milk' => $definition['contains_milk'],
                    'shelf_life_days' => $definition['shelf_life_days'],
                    'status' => 'active',
                    'is_active' => true,
                ]
            );

            DB::table('erp_product_lines')->updateOrInsert(
                [
                    'product_id' => $product->id,
                    'production_line_id' => $line->id,
                ],
                [
                    'is_preferred' => true,

                    'nominal_rate_units_per_hour' =>
                        $line->nominal_capacity_units_per_hour,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $route = ErpProductRoute::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'version' => 1,
                ],
                [
                    'code' => 'ROUTE_' . $definition['code'] . '_V1',

                    'name' => $definition['name']
                        . ' Manufacturing Route',

                    'description' =>
                        'Simulated manufacturing route for prototype use.',

                    'is_active' => true,
                ]
            );

            foreach ($stages as $stage) {
                ErpProductRouteStep::query()->updateOrCreate(
                    [
                        'product_route_id' => $route->id,
                        'sequence_order' => $stage->sequence_order,
                    ],
                    [
                        'process_stage_id' => $stage->id,
                        'is_required' => true,

                        'target_duration_minutes' =>
                            $this->durationForStage($stage->code),

                        'notes' =>
                            'Simulated process target for prototype data.',
                    ]
                );
            }
        }
    }

    private function durationForStage(string $stageCode): ?int
    {
        return [
            'FORMULA_DEVELOPMENT' => 240,
            'RAW_MATERIAL_TRANSFER' => 30,
            'INGREDIENT_MIXING' => 60,
            'HOMOGENIZATION_STERILIZATION' => 45,
            'FILLING' => 120,
            'CLOSURE_APPLICATION' => 30,
            'CARTONING' => 45,
            'PALLETIZATION_QUALITY_WAIT' => 90,
            'FINISHED_PRODUCT_RELEASE' => 20,
        ][$stageCode] ?? null;
    }

    private function productDefinitions(): array
    {
        return [
            [
                'code' => 'SIM_PREMIUM_ORANGE_1L',
                'name' => 'Valencia Premium Orange 1 L',
                'family' => 'VALENCIA_PREMIUM',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Orange',
                'beverage_type' => 'pure_juice',
                'contains_milk' => false,
                'shelf_life_days' => 270,
            ],
            [
                'code' => 'SIM_PREMIUM_PINEAPPLE_1L',
                'name' => 'Valencia Premium Pineapple 1 L',
                'family' => 'VALENCIA_PREMIUM',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Pineapple',
                'beverage_type' => 'pure_juice',
                'contains_milk' => false,
                'shelf_life_days' => 270,
            ],
            [
                'code' => 'SIM_PREMIUM_ORANGE_250ML',
                'name' => 'Valencia Premium Orange 250 mL',
                'family' => 'VALENCIA_PREMIUM',
                'format' => 'CARTON_250ML',
                'line' => 'SIM_LINE_250ML',
                'flavor' => 'Orange',
                'beverage_type' => 'pure_juice',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_ESSENTIEL_MANGO_1L',
                'name' => 'Valencia Essentiel Mango 1 L',
                'family' => 'VALENCIA_ESSENTIEL',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Mango',
                'beverage_type' => 'nectar',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_ESSENTIEL_GUAVA_1L',
                'name' => 'Valencia Essentiel Guava 1 L',
                'family' => 'VALENCIA_ESSENTIEL',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Guava',
                'beverage_type' => 'nectar',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_CLASSICS_MIXED_FRUIT_1L',
                'name' => 'Valencia Classics Mixed Fruit 1 L',
                'family' => 'VALENCIA_CLASSICS',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Mixed Fruit',
                'beverage_type' => 'nectar',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_CLASSICS_APPLE_1L',
                'name' => 'Valencia Classics Apple 1 L',
                'family' => 'VALENCIA_CLASSICS',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Apple',
                'beverage_type' => 'nectar',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_LACTE_STRAWBERRY_200ML',
                'name' => 'Valencia Lacté Strawberry 200 mL',
                'family' => 'VALENCIA_LACTE',
                'format' => 'CARTON_200ML',
                'line' => 'SIM_LINE_200ML',
                'flavor' => 'Strawberry',
                'beverage_type' => 'juice_milk_blend',
                'contains_milk' => true,
                'shelf_life_days' => 180,
            ],
            [
                'code' => 'SIM_LACTE_BANANA_200ML',
                'name' => 'Valencia Lacté Banana 200 mL',
                'family' => 'VALENCIA_LACTE',
                'format' => 'CARTON_200ML',
                'line' => 'SIM_LINE_200ML',
                'flavor' => 'Banana',
                'beverage_type' => 'juice_milk_blend',
                'contains_milk' => true,
                'shelf_life_days' => 180,
            ],
            [
                'code' => 'SIM_TWIST_TROPICAL_250ML',
                'name' => 'Valencia Twist Tropical 250 mL',
                'family' => 'VALENCIA_TWIST',
                'format' => 'CARTON_250ML',
                'line' => 'SIM_LINE_250ML',
                'flavor' => 'Tropical',
                'beverage_type' => 'fruit_drink',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_TWIST_RED_FRUITS_250ML',
                'name' => 'Valencia Twist Red Fruits 250 mL',
                'family' => 'VALENCIA_TWIST',
                'format' => 'CARTON_250ML',
                'line' => 'SIM_LINE_250ML',
                'flavor' => 'Red Fruits',
                'beverage_type' => 'fruit_drink',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_ICE_TEA_PEACH_1L',
                'name' => 'Valencia Ice Tea Peach 1 L',
                'family' => 'VALENCIA_ICE_TEA',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Peach',
                'beverage_type' => 'iced_tea',
                'contains_milk' => false,
                'shelf_life_days' => 300,
            ],
            [
                'code' => 'SIM_ICE_TEA_LEMON_1L',
                'name' => 'Valencia Ice Tea Lemon 1 L',
                'family' => 'VALENCIA_ICE_TEA',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Lemon',
                'beverage_type' => 'iced_tea',
                'contains_milk' => false,
                'shelf_life_days' => 300,
            ],
            [
                'code' => 'SIM_JUPER_ORANGE_200ML',
                'name' => 'Valencia Juper Orange 200 mL',
                'family' => 'VALENCIA_JUPER',
                'format' => 'CARTON_200ML',
                'line' => 'SIM_LINE_200ML',
                'flavor' => 'Orange',
                'beverage_type' => 'fruit_drink',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_MAXI_MIXED_FRUIT_1L',
                'name' => 'Valencia Maxi Mixed Fruit 1 L',
                'family' => 'VALENCIA_MAXI',
                'format' => 'CARTON_1L',
                'line' => 'SIM_LINE_1L',
                'flavor' => 'Mixed Fruit',
                'beverage_type' => 'fruit_drink',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_ABTAL_APPLE_200ML',
                'name' => 'Valencia Abtal Apple 200 mL',
                'family' => 'VALENCIA_ABTAL',
                'format' => 'CARTON_200ML',
                'line' => 'SIM_LINE_200ML',
                'flavor' => 'Apple',
                'beverage_type' => 'fruit_drink',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
            [
                'code' => 'SIM_PLAISIR_MANGO_250ML',
                'name' => 'Valencia Plaisir Mango 250 mL',
                'family' => 'VALENCIA_PLAISIR',
                'format' => 'CARTON_250ML',
                'line' => 'SIM_LINE_250ML',
                'flavor' => 'Mango',
                'beverage_type' => 'fruit_drink',
                'contains_milk' => false,
                'shelf_life_days' => 240,
            ],
        ];
    }
}