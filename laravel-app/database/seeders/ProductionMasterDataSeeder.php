<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\Operator;
use App\Models\OperatorAssignment;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionLine;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use LogicException;

class ProductionMasterDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SOURCE_SYSTEM = 'simulated_sage';

    private const SOURCE_VERSION = 1;

    private const SOURCE_UPDATED_AT =
        '2026-07-01 00:00:00';

    private const ASSIGNMENT_START_DATE =
        '2026-07-01';

    /**
     * Insert deterministic simulated production master data.
     */
    public function run(): void
    {
        /*
         * Synthetic development data must never be inserted into
         * a production environment.
         */
        if (app()->environment('production')) {
            throw new LogicException(
                'Simulated production master data cannot be seeded '
                .'in the production environment.'
            );
        }

        DB::transaction(
            function (): void {
                $families =
                    $this->seedProductFamilies();

                $this->seedProducts(
                    $families
                );

                $lines =
                    $this->seedProductionLines();

                $this->seedMachines(
                    $lines
                );

                $shifts =
                    $this->seedShifts();

                $operators =
                    $this->seedOperators();

                $this->seedOperatorAssignments(
                    operators: $operators,
                    lines: $lines,
                    shifts: $shifts
                );
            },
            3
        );
    }

    /**
     * @return array<string, ProductFamily>
     */
    private function seedProductFamilies(): array
    {
        $records = [
            [
                'external_id' => 'PF-001',
                'code' => 'VAL-PREMIUM',
                'name' => 'Valencia Premium',
                'description' =>
                    'Simulated premium pure-juice range.',
            ],
            [
                'external_id' => 'PF-002',
                'code' => 'VAL-ESSENTIAL-CLASSICS',
                'name' => 'Valencia Essentiel & Classics',
                'description' =>
                    'Simulated traditional fruit-nectar range.',
            ],
            [
                'external_id' => 'PF-003',
                'code' => 'VAL-LACTE-TWIST',
                'name' => 'Valencia Lacté & Twist',
                'description' =>
                    'Simulated fruit-juice and milk-blend range.',
            ],
            [
                'external_id' => 'PF-004',
                'code' => 'VAL-ICE-TEA',
                'name' => 'Valencia Ice Tea',
                'description' =>
                    'Simulated flavored iced-tea range.',
            ],
            [
                'external_id' => 'PF-005',
                'code' => 'VAL-FAMILY-RANGES',
                'name' =>
                    'Valencia Juper, Maxi, Abtal & Plaisir',
                'description' =>
                    'Simulated youth and family product range.',
            ],
        ];

        $families = [];

        foreach ($records as $record) {
            $family = ProductFamily::query()
                ->where(
                    'source_system',
                    self::SOURCE_SYSTEM
                )
                ->where(
                    'external_id',
                    $record['external_id']
                )
                ->orWhere(
                    'code',
                    $record['code']
                )
                ->first()
                ?? new ProductFamily();

            $attributes = [
                'code' => $record['code'],
                'name' => $record['name'],
                'description' =>
                    $record['description'],
                'is_active' => true,
            ];

            $this->persistSimulatedRecord(
                model: $family,
                attributes: $attributes,
                externalId: $record['external_id']
            );

            $families[$record['code']] =
                $family;
        }

        return $families;
    }

    /**
     * @param array<string, ProductFamily> $families
     */
    private function seedProducts(
        array $families
    ): void {
        $records = [
            [
                'external_id' => 'PRD-001',
                'family' => 'VAL-PREMIUM',
                'code' => 'VP-ORANGE-1L',
                'sku' => 'VP-OR-1000',
                'name' =>
                    'Valencia Premium Orange 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-002',
                'family' => 'VAL-PREMIUM',
                'code' => 'VP-PINEAPPLE-1L',
                'sku' => 'VP-PI-1000',
                'name' =>
                    'Valencia Premium Pineapple 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-003',
                'family' => 'VAL-PREMIUM',
                'code' => 'VP-ORANGE-250',
                'sku' => 'VP-OR-0250',
                'name' =>
                    'Valencia Premium Orange 250 mL',
                'package_format' => '250 mL',
                'nominal_volume' => 0.250,
            ],
            [
                'external_id' => 'PRD-004',
                'family' =>
                    'VAL-ESSENTIAL-CLASSICS',
                'code' => 'VE-ORANGE-NECTAR-1L',
                'sku' => 'VE-OR-1000',
                'name' =>
                    'Valencia Essentiel Orange Nectar 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-005',
                'family' =>
                    'VAL-ESSENTIAL-CLASSICS',
                'code' => 'VC-MANGO-NECTAR-1L',
                'sku' => 'VC-MA-1000',
                'name' =>
                    'Valencia Classics Mango Nectar 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-006',
                'family' =>
                    'VAL-ESSENTIAL-CLASSICS',
                'code' => 'VC-MULTIFRUIT-1L',
                'sku' => 'VC-MF-1000',
                'name' =>
                    'Valencia Classics Multifruit 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-007',
                'family' => 'VAL-LACTE-TWIST',
                'code' => 'VL-STRAWBERRY-200',
                'sku' => 'VL-ST-0200',
                'name' =>
                    'Valencia Lacté Strawberry 200 mL',
                'package_format' => '200 mL',
                'nominal_volume' => 0.200,
            ],
            [
                'external_id' => 'PRD-008',
                'family' => 'VAL-LACTE-TWIST',
                'code' => 'VL-BANANA-200',
                'sku' => 'VL-BA-0200',
                'name' =>
                    'Valencia Lacté Banana 200 mL',
                'package_format' => '200 mL',
                'nominal_volume' => 0.200,
            ],
            [
                'external_id' => 'PRD-009',
                'family' => 'VAL-LACTE-TWIST',
                'code' => 'VT-PEACH-200',
                'sku' => 'VT-PE-0200',
                'name' =>
                    'Valencia Twist Peach-Milk 200 mL',
                'package_format' => '200 mL',
                'nominal_volume' => 0.200,
            ],
            [
                'external_id' => 'PRD-010',
                'family' => 'VAL-ICE-TEA',
                'code' => 'VIT-PEACH-1L',
                'sku' => 'VIT-PE-1000',
                'name' =>
                    'Valencia Ice Tea Peach 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-011',
                'family' => 'VAL-ICE-TEA',
                'code' => 'VIT-LEMON-1L',
                'sku' => 'VIT-LE-1000',
                'name' =>
                    'Valencia Ice Tea Lemon 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-012',
                'family' => 'VAL-ICE-TEA',
                'code' => 'VIT-PEACH-250',
                'sku' => 'VIT-PE-0250',
                'name' =>
                    'Valencia Ice Tea Peach 250 mL',
                'package_format' => '250 mL',
                'nominal_volume' => 0.250,
            ],
            [
                'external_id' => 'PRD-013',
                'family' => 'VAL-FAMILY-RANGES',
                'code' => 'VJ-ORANGE-200',
                'sku' => 'VJ-OR-0200',
                'name' =>
                    'Valencia Juper Orange 200 mL',
                'package_format' => '200 mL',
                'nominal_volume' => 0.200,
            ],
            [
                'external_id' => 'PRD-014',
                'family' => 'VAL-FAMILY-RANGES',
                'code' => 'VM-MULTIFRUIT-1L',
                'sku' => 'VM-MF-1000',
                'name' =>
                    'Valencia Maxi Multifruit 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
            [
                'external_id' => 'PRD-015',
                'family' => 'VAL-FAMILY-RANGES',
                'code' => 'VA-ORANGE-200',
                'sku' => 'VA-OR-0200',
                'name' =>
                    'Valencia Abtal Orange 200 mL',
                'package_format' => '200 mL',
                'nominal_volume' => 0.200,
            ],
            [
                'external_id' => 'PRD-016',
                'family' => 'VAL-FAMILY-RANGES',
                'code' => 'VPL-PEACH-1L',
                'sku' => 'VPL-PE-1000',
                'name' =>
                    'Valencia Plaisir Peach 1 L',
                'package_format' => '1 L',
                'nominal_volume' => 1.000,
            ],
        ];

        foreach ($records as $record) {
            $product = Product::query()
                ->where(
                    'source_system',
                    self::SOURCE_SYSTEM
                )
                ->where(
                    'external_id',
                    $record['external_id']
                )
                ->orWhere(
                    'code',
                    $record['code']
                )
                ->first()
                ?? new Product();

            $attributes = [
                'product_family_id' =>
                    $families[
                        $record['family']
                    ]->getKey(),

                'code' => $record['code'],
                'sku' => $record['sku'],
                'name' => $record['name'],
                'base_unit' => 'bottle',
                'package_format' =>
                    $record['package_format'],
                'nominal_volume' =>
                    $record['nominal_volume'],
                'is_active' => true,
            ];

            $this->persistSimulatedRecord(
                model: $product,
                attributes: $attributes,
                externalId: $record['external_id']
            );
        }
    }

    /**
     * @return array<string, ProductionLine>
     */
    private function seedProductionLines(): array
    {
        $records = [
            [
                'external_id' => 'PL-001',
                'code' => 'LINE-01',
                'name' =>
                    'Premium and Classics Line',
                'plant_area' =>
                    'Production Hall A',
                'description' =>
                    'Simulated line for premium juices and nectars.',
                'capacity' => 12000,
            ],
            [
                'external_id' => 'PL-002',
                'code' => 'LINE-02',
                'name' =>
                    'Lacté and Twist Line',
                'plant_area' =>
                    'Production Hall B',
                'description' =>
                    'Simulated line for fruit-and-milk products.',
                'capacity' => 9000,
            ],
            [
                'external_id' => 'PL-003',
                'code' => 'LINE-03',
                'name' =>
                    'Ice Tea and Family Formats Line',
                'plant_area' =>
                    'Production Hall C',
                'description' =>
                    'Simulated line for iced tea and family formats.',
                'capacity' => 14000,
            ],
        ];

        $lines = [];

        foreach ($records as $record) {
            $line = ProductionLine::query()
                ->where(
                    'source_system',
                    self::SOURCE_SYSTEM
                )
                ->where(
                    'external_id',
                    $record['external_id']
                )
                ->orWhere(
                    'code',
                    $record['code']
                )
                ->first()
                ?? new ProductionLine();

            $attributes = [
                'code' => $record['code'],
                'name' => $record['name'],
                'plant_area' =>
                    $record['plant_area'],
                'description' =>
                    $record['description'],
                'nominal_capacity_per_hour' =>
                    $record['capacity'],
                'capacity_unit' =>
                    'bottles/hour',
                'is_active' => true,
            ];

            $this->persistSimulatedRecord(
                model: $line,
                attributes: $attributes,
                externalId: $record['external_id']
            );

            $lines[$record['code']] =
                $line;
        }

        return $lines;
    }

    /**
     * Every simulated line receives seven machines.
     *
     * @param array<string, ProductionLine> $lines
     */
    private function seedMachines(
        array $lines
    ): void {
        $machineTemplate = [
            [
                'suffix' => 'PAST',
                'name' => 'Pasteurizer',
                'type' => 'pasteurizer',
                'critical' => true,
            ],
            [
                'suffix' => 'MIX',
                'name' => 'Mixing Unit',
                'type' => 'mixing-unit',
                'critical' => true,
            ],
            [
                'suffix' => 'BUF',
                'name' => 'Buffer Tank',
                'type' => 'buffer-tank',
                'critical' => false,
            ],
            [
                'suffix' => 'FILL',
                'name' => 'Filling Machine',
                'type' => 'filler',
                'critical' => true,
            ],
            [
                'suffix' => 'CAP',
                'name' => 'Capping Machine',
                'type' => 'capper',
                'critical' => false,
            ],
            [
                'suffix' => 'LAB',
                'name' => 'Labelling Machine',
                'type' => 'labeller',
                'critical' => false,
            ],
            [
                'suffix' => 'PACK',
                'name' => 'Packaging Machine',
                'type' => 'case-packer',
                'critical' => true,
            ],
        ];

        foreach ($lines as $lineCode => $line) {
            foreach (
                $machineTemplate as $index => $definition
            ) {
                $sequence = $index + 1;

                $externalId = sprintf(
                    'M-%s-%02d',
                    $lineCode,
                    $sequence
                );

                $code = sprintf(
                    '%s-%s',
                    $lineCode,
                    $definition['suffix']
                );

                $machine = Machine::query()
                    ->where(
                        'source_system',
                        self::SOURCE_SYSTEM
                    )
                    ->where(
                        'external_id',
                        $externalId
                    )
                    ->orWhere('code', $code)
                    ->first()
                    ?? new Machine();

                $attributes = [
                    'production_line_id' =>
                        $line->getKey(),

                    'code' => $code,

                    'name' => sprintf(
                        '%s %s',
                        $definition['name'],
                        $lineCode
                    ),

                    'machine_type' =>
                        $definition['type'],

                    'manufacturer' =>
                        'Simulated Industrial Systems',

                    'model' => sprintf(
                        'SIM-%s-%02d',
                        $definition['suffix'],
                        $sequence
                    ),

                    'serial_number' => sprintf(
                        'SIM-%s-%03d',
                        $lineCode,
                        $sequence
                    ),

                    'sequence_number' =>
                        $sequence,

                    'is_critical' =>
                        $definition['critical'],

                    'is_active' => true,
                ];

                $this->persistSimulatedRecord(
                    model: $machine,
                    attributes: $attributes,
                    externalId: $externalId
                );
            }
        }
    }

    /**
     * @return array<string, Shift>
     */
    private function seedShifts(): array
    {
        $records = [
            [
                'external_id' => 'SH-001',
                'code' => 'SHIFT-A',
                'name' => 'Morning shift',
                'starts_at' => '06:00:00',
                'ends_at' => '14:00:00',
                'crosses_midnight' => false,
            ],
            [
                'external_id' => 'SH-002',
                'code' => 'SHIFT-B',
                'name' => 'Afternoon shift',
                'starts_at' => '14:00:00',
                'ends_at' => '22:00:00',
                'crosses_midnight' => false,
            ],
            [
                'external_id' => 'SH-003',
                'code' => 'SHIFT-C',
                'name' => 'Night shift',
                'starts_at' => '22:00:00',
                'ends_at' => '06:00:00',
                'crosses_midnight' => true,
            ],
        ];

        $shifts = [];

        foreach ($records as $record) {
            $shift = Shift::query()
                ->where(
                    'source_system',
                    self::SOURCE_SYSTEM
                )
                ->where(
                    'external_id',
                    $record['external_id']
                )
                ->orWhere(
                    'code',
                    $record['code']
                )
                ->first()
                ?? new Shift();

            $attributes = [
                'code' => $record['code'],
                'name' => $record['name'],
                'starts_at' =>
                    $record['starts_at'],
                'ends_at' =>
                    $record['ends_at'],
                'crosses_midnight' =>
                    $record['crosses_midnight'],
                'is_active' => true,
            ];

            $this->persistSimulatedRecord(
                model: $shift,
                attributes: $attributes,
                externalId: $record['external_id']
            );

            $shifts[$record['code']] =
                $shift;
        }

        return $shifts;
    }

    /**
     * Create employee records only.
     *
     * No login account, password, role, or permission is created.
     *
     * @return array<string, Operator>
     */
    private function seedOperators(): array
    {
        $operators = [];

        for ($number = 1; $number <= 9; $number++) {
            $formattedNumber = str_pad(
                (string) $number,
                2,
                '0',
                STR_PAD_LEFT
            );

            $externalId =
                'OP-'.$formattedNumber;

            $employeeCode =
                'SIM-OP-'.$formattedNumber;

            $operator = Operator::query()
                ->where(
                    'source_system',
                    self::SOURCE_SYSTEM
                )
                ->where(
                    'external_id',
                    $externalId
                )
                ->orWhere(
                    'employee_code',
                    $employeeCode
                )
                ->first()
                ?? new Operator();

            $attributes = [
                'employee_code' =>
                    $employeeCode,

                'first_name' => 'Simulated',

                'last_name' =>
                    'Operator '.$formattedNumber,

                'email' => sprintf(
                    'sim.operator%s@smartfactory.test',
                    $formattedNumber
                ),

                'phone' => null,
                'hired_on' => null,
                'is_active' => true,
            ];

            $this->persistSimulatedRecord(
                model: $operator,
                attributes: $attributes,
                externalId: $externalId
            );

            /*
             * Deliberately keep the authentication link empty.
             */
            if ($operator->user_id !== null) {
                $operator->forceFill([
                    'user_id' => null,
                ])->save();
            }

            $operators[$externalId] =
                $operator;
        }

        return $operators;
    }

    /**
     * Assign one simulated operator to every line and shift.
     *
     * @param array<string, Operator> $operators
     * @param array<string, ProductionLine> $lines
     * @param array<string, Shift> $shifts
     */
    private function seedOperatorAssignments(
        array $operators,
        array $lines,
        array $shifts
    ): void {
        $records = [
            ['OP-01', 'LINE-01', 'SHIFT-A'],
            ['OP-02', 'LINE-01', 'SHIFT-B'],
            ['OP-03', 'LINE-01', 'SHIFT-C'],

            ['OP-04', 'LINE-02', 'SHIFT-A'],
            ['OP-05', 'LINE-02', 'SHIFT-B'],
            ['OP-06', 'LINE-02', 'SHIFT-C'],

            ['OP-07', 'LINE-03', 'SHIFT-A'],
            ['OP-08', 'LINE-03', 'SHIFT-B'],
            ['OP-09', 'LINE-03', 'SHIFT-C'],
        ];

        foreach (
            $records as $index => [
                $operatorExternalId,
                $lineCode,
                $shiftCode,
            ]
        ) {
            $externalId = sprintf(
                'OA-%03d',
                $index + 1
            );

            $assignment =
                OperatorAssignment::query()
                    ->where(
                        'source_system',
                        self::SOURCE_SYSTEM
                    )
                    ->where(
                        'external_id',
                        $externalId
                    )
                    ->first()
                    ?? new OperatorAssignment();

            $attributes = [
                'operator_id' =>
                    $operators[
                        $operatorExternalId
                    ]->getKey(),

                'production_line_id' =>
                    $lines[$lineCode]
                        ->getKey(),

                'shift_id' =>
                    $shifts[$shiftCode]
                        ->getKey(),

                'starts_on' =>
                    self::ASSIGNMENT_START_DATE,

                'ends_on' => null,
                'is_primary' => true,
                'is_active' => true,
            ];

            $this->persistSimulatedRecord(
                model: $assignment,
                attributes: $attributes,
                externalId: $externalId
            );

            /*
             * Seeder-generated assignments have no human administrator.
             */
            if ($assignment->assigned_by !== null) {
                $assignment->forceFill([
                    'assigned_by' => null,
                ])->save();
            }
        }
    }

    /**
     * Save one deterministic simulated-source record.
     *
     * @param array<string, mixed> $attributes
     *
     * @throws JsonException
     */
    private function persistSimulatedRecord(
        Model $model,
        array $attributes,
        string $externalId
    ): void {
        $sourceTimestamp =
            CarbonImmutable::parse(
                self::SOURCE_UPDATED_AT
            );

        $checksumPayload = [
            'source_system' =>
                self::SOURCE_SYSTEM,

            'external_id' =>
                $externalId,

            'attributes' =>
                $attributes,
        ];

        $model->fill(
            $attributes
        );

        $model->forceFill([
            'source_system' =>
                self::SOURCE_SYSTEM,

            'external_id' =>
                $externalId,

            'source_version' =>
                self::SOURCE_VERSION,

            'source_checksum' =>
                $this->checksum(
                    $checksumPayload
                ),

            'source_updated_at' =>
                $sourceTimestamp,

            'last_synced_at' =>
                $sourceTimestamp,
        ]);

        $model->save();
    }

    /**
     * Generate a stable SHA-256 checksum.
     *
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    private function checksum(
        array $payload
    ): string {
        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            )
        );
    }
}