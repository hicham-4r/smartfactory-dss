<?php

namespace Database\Seeders;

use App\Models\ErpPackagingFormat;
use App\Models\ErpProcessStage;
use App\Models\ErpProductFamily;
use App\Models\ErpProductionLine;
use Illuminate\Database\Seeder;

class ErpReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProductFamilies();
        $this->seedPackagingFormats();
        $this->seedProcessStages();
        $this->seedProductionLines();
    }

    private function seedProductFamilies(): void
    {
        $families = [
            [
                'code' => 'VALENCIA_PREMIUM',
                'name' => 'Valencia Premium',
                'description' => 'Premium pure juice range.',
            ],
            [
                'code' => 'VALENCIA_ESSENTIEL',
                'name' => 'Valencia Essentiel',
                'description' => 'Traditional fruit nectar range.',
            ],
            [
                'code' => 'VALENCIA_CLASSICS',
                'name' => 'Valencia Classics',
                'description' => 'Classic fruit nectar range.',
            ],
            [
                'code' => 'VALENCIA_LACTE',
                'name' => 'Valencia Lacté',
                'description' => 'Fruit juice and milk blend range.',
            ],
            [
                'code' => 'VALENCIA_TWIST',
                'name' => 'Valencia Twist',
                'description' => 'Mixed fruit beverage range.',
            ],
            [
                'code' => 'VALENCIA_ICE_TEA',
                'name' => 'Valencia Ice Tea',
                'description' => 'Flavored iced tea range.',
            ],
            [
                'code' => 'VALENCIA_JUPER',
                'name' => 'Valencia Juper',
                'description' => 'Youth and family beverage range.',
            ],
            [
                'code' => 'VALENCIA_MAXI',
                'name' => 'Valencia Maxi',
                'description' => 'Family-format beverage range.',
            ],
            [
                'code' => 'VALENCIA_ABTAL',
                'name' => 'Valencia Abtal',
                'description' => 'Youth-oriented beverage range.',
            ],
            [
                'code' => 'VALENCIA_PLAISIR',
                'name' => 'Valencia Plaisir',
                'description' => 'General fruit beverage range.',
            ],
        ];

        foreach ($families as $family) {
            ErpProductFamily::query()->updateOrCreate(
                ['code' => $family['code']],
                $family
            );
        }
    }

    private function seedPackagingFormats(): void
    {
        $formats = [
            [
                'code' => 'CARTON_1L',
                'label' => 'Carton 1 L',
                'volume_ml' => 1000,
                'package_type' => 'carton',
                'closure_type' => 'cap',
                'has_straw' => false,
            ],
            [
                'code' => 'CARTON_200ML',
                'label' => 'Carton 200 mL',
                'volume_ml' => 200,
                'package_type' => 'carton',
                'closure_type' => 'straw',
                'has_straw' => true,
            ],
            [
                'code' => 'CARTON_250ML',
                'label' => 'Carton 250 mL',
                'volume_ml' => 250,
                'package_type' => 'carton',
                'closure_type' => 'straw',
                'has_straw' => true,
            ],
        ];

        foreach ($formats as $format) {
            ErpPackagingFormat::query()->updateOrCreate(
                ['code' => $format['code']],
                $format
            );
        }
    }

    private function seedProcessStages(): void
    {
        $stages = [
            [
                'code' => 'FORMULA_DEVELOPMENT',
                'name' => 'Formula development',
                'sequence_order' => 1,
                'description' => 'Development of the juice formula in the laboratory.',
            ],
            [
                'code' => 'RAW_MATERIAL_TRANSFER',
                'name' => 'Raw-material transfer',
                'sequence_order' => 2,
                'description' => 'Transfer of raw materials to the processing area.',
            ],
            [
                'code' => 'INGREDIENT_MIXING',
                'name' => 'Ingredient mixing',
                'sequence_order' => 3,
                'description' => 'Mixing ingredients in a processing tank.',
            ],
            [
                'code' => 'HOMOGENIZATION_STERILIZATION',
                'name' => 'Homogenization and sterilization',
                'sequence_order' => 4,
                'description' => 'Preparation and sterilization before filling.',
            ],
            [
                'code' => 'FILLING',
                'name' => 'Filling',
                'sequence_order' => 5,
                'description' => 'Filling into 1 L, 200 mL, or 250 mL packages.',
            ],
            [
                'code' => 'CLOSURE_APPLICATION',
                'name' => 'Cap or straw application',
                'sequence_order' => 6,
                'description' => 'Application of caps or drinking straws.',
            ],
            [
                'code' => 'CARTONING',
                'name' => 'Lot grouping and cartoning',
                'sequence_order' => 7,
                'description' => 'Grouping products into lots and cartons.',
            ],
            [
                'code' => 'PALLETIZATION_QUALITY_WAIT',
                'name' => 'Palletization and quality waiting area',
                'sequence_order' => 8,
                'description' => 'Palletization and transfer for quality analyses.',
            ],
            [
                'code' => 'FINISHED_PRODUCT_RELEASE',
                'name' => 'Finished-product release',
                'sequence_order' => 9,
                'description' => 'Release as finished products after validation.',
            ],
        ];

        foreach ($stages as $stage) {
            ErpProcessStage::query()->updateOrCreate(
                ['code' => $stage['code']],
                $stage
            );
        }
    }

    private function seedProductionLines(): void
    {
        // These are simulated prototype lines, not real company line names.
        $lines = [
            [
                'code' => 'SIM_LINE_1L',
                'name' => 'Simulated 1 L Production Line',
                'description' => 'Prototype line for 1 L packaged products.',
                'status' => 'active',
                'nominal_capacity_units_per_hour' => 6000,
            ],
            [
                'code' => 'SIM_LINE_200ML',
                'name' => 'Simulated 200 mL Production Line',
                'description' => 'Prototype line for 200 mL products.',
                'status' => 'active',
                'nominal_capacity_units_per_hour' => 12000,
            ],
            [
                'code' => 'SIM_LINE_250ML',
                'name' => 'Simulated 250 mL Production Line',
                'description' => 'Prototype line for 250 mL products.',
                'status' => 'active',
                'nominal_capacity_units_per_hour' => 10000,
            ],
        ];

        foreach ($lines as $line) {
            ErpProductionLine::query()->updateOrCreate(
                ['code' => $line['code']],
                $line
            );
        }
    }
}