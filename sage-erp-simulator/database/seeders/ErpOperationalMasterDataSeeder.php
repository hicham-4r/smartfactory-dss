<?php

namespace Database\Seeders;

use App\Models\ErpMachine;
use App\Models\ErpOperator;
use App\Models\ErpOperatorAssignment;
use App\Models\ErpProcessStage;
use App\Models\ErpProductionLine;
use App\Models\ErpShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ErpOperationalMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedShifts();
        $this->seedMachinesAndLineAssignments();
        $this->seedOperatorsAndAssignments();
    }

    private function seedShifts(): void
    {
        $shifts = [
            [
                'code' => 'SHIFT_MORNING',
                'name' => 'Morning Shift',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'crosses_midnight' => false,
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'code' => 'SHIFT_AFTERNOON',
                'name' => 'Afternoon Shift',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'crosses_midnight' => false,
                'status' => 'active',
                'is_active' => true,
            ],
            [
                'code' => 'SHIFT_NIGHT',
                'name' => 'Night Shift',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'crosses_midnight' => true,
                'status' => 'active',
                'is_active' => true,
            ],
        ];

        foreach ($shifts as $shift) {
            ErpShift::query()->updateOrCreate(
                ['code' => $shift['code']],
                $shift
            );
        }
    }

    private function seedMachinesAndLineAssignments(): void
    {
        $stages = ErpProcessStage::query()
            ->pluck('id', 'code');

        $machineTemplates = [
            [
                'code' => 'MIXER',
                'name' => 'Ingredient Mixing Tank',
                'type' => 'mixing_tank',
                'stage' => 'INGREDIENT_MIXING',
                'criticality' => 'high',
            ],
            [
                'code' => 'HOMOGENIZER',
                'name' => 'Homogenizer',
                'type' => 'homogenizer',
                'stage' => 'HOMOGENIZATION_STERILIZATION',
                'criticality' => 'high',
            ],
            [
                'code' => 'STERILIZER',
                'name' => 'Sterilization Unit',
                'type' => 'sterilizer',
                'stage' => 'HOMOGENIZATION_STERILIZATION',
                'criticality' => 'critical',
            ],
            [
                'code' => 'FILLER',
                'name' => 'Filling Machine',
                'type' => 'filling_machine',
                'stage' => 'FILLING',
                'criticality' => 'critical',
            ],
            [
                'code' => 'CLOSURE',
                'name' => 'Cap and Straw Applicator',
                'type' => 'closure_applicator',
                'stage' => 'CLOSURE_APPLICATION',
                'criticality' => 'high',
            ],
            [
                'code' => 'CARTONER',
                'name' => 'Cartoning Machine',
                'type' => 'cartoning_machine',
                'stage' => 'CARTONING',
                'criticality' => 'medium',
            ],
            [
                'code' => 'PALLETIZER',
                'name' => 'Palletizing Machine',
                'type' => 'palletizer',
                'stage' => 'PALLETIZATION_QUALITY_WAIT',
                'criticality' => 'medium',
            ],
        ];

        $lines = ErpProductionLine::query()
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            foreach ($machineTemplates as $index => $template) {
                $position = $index + 1;

                $machineCode = sprintf(
                    '%s_%s',
                    $line->code,
                    $template['code']
                );

                $machine = ErpMachine::query()->updateOrCreate(
                    ['code' => $machineCode],
                    [
                        'name' => $line->name
                            . ' - '
                            . $template['name'],

                        'machine_type' => $template['type'],

                        'manufacturer' =>
                            'Simulated Equipment Manufacturer',

                        'model_reference' => sprintf(
                            'SIM-%s-%02d',
                            $template['code'],
                            $position
                        ),

                        'serial_number' => sprintf(
                            'SIM-%s-M%02d',
                            $line->code,
                            $position
                        ),

                        'status' => 'operational',
                        'criticality' => $template['criticality'],
                        'installation_date' => '2025-01-15',
                        'is_active' => true,
                    ]
                );

                DB::table('erp_line_machines')->updateOrInsert(
                    [
                        'machine_id' => $machine->id,
                    ],
                    [
                        'production_line_id' => $line->id,

                        'process_stage_id' =>
                            $stages[$template['stage']] ?? null,

                        'sequence_order' => $position,

                        'station_code' => sprintf(
                            'STATION_%02d',
                            $position
                        ),

                        'is_primary' => true,
                        'assigned_at' => '2025-01-15',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedOperatorsAndAssignments(): void
    {
        $lines = ErpProductionLine::query()
            ->orderBy('id')
            ->get();

        $shifts = ErpShift::query()
            ->orderBy('start_time')
            ->get();

        $operatorNumber = 1;

        foreach ($lines as $line) {
            foreach ($shifts as $shift) {
                for ($position = 1; $position <= 2; $position++) {
                    $employeeCode = sprintf(
                        'SIM-OP-%03d',
                        $operatorNumber
                    );

                    $operator = ErpOperator::query()->updateOrCreate(
                        [
                            'employee_code' => $employeeCode,
                        ],
                        [
                            'first_name' => 'Simulated',
                            'last_name' => sprintf(
                                'Operator %03d',
                                $operatorNumber
                            ),

                            'email' => sprintf(
                                'sim.operator%03d@example.test',
                                $operatorNumber
                            ),

                            'phone' => null,
                            'hire_date' => '2025-01-01',
                            'status' => 'active',
                            'is_active' => true,
                        ]
                    );

                    ErpOperatorAssignment::query()->updateOrCreate(
                        [
                            'operator_id' => $operator->id,
                            'assigned_from' => '2026-01-01',
                        ],
                        [
                            'production_line_id' => $line->id,
                            'shift_id' => $shift->id,

                            'role_on_line' => $position === 1
                                ? 'primary_line_operator'
                                : 'assistant_line_operator',

                            'assigned_until' => null,
                            'is_primary' => $position === 1,
                            'is_active' => true,
                        ]
                    );

                    $operatorNumber++;
                }
            }
        }
    }
}