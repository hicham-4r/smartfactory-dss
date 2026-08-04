<?php

namespace Database\Seeders;

use App\Models\ErpDowntimeEvent;
use App\Models\ErpMachine;
use App\Models\ErpMachineStatusEvent;
use App\Models\ErpMaintenanceHistory;
use App\Models\ErpProductionBatch;
use App\Models\ErpShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ErpMaintenanceDowntimeDataSeeder extends Seeder
{
    private const HISTORY_DAYS = 90;

    public function run(): void
    {
        DB::transaction(function (): void {
            mt_srand(20260725);

            $this->deleteExistingData();

            $machines = ErpMachine::query()
                ->with('productionLines')
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            $shifts = ErpShift::query()
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get();

            if ($machines->isEmpty() || $shifts->isEmpty()) {
                throw new RuntimeException(
                    'Machines and shifts must be seeded first.'
                );
            }

            $batches = $this->productionBatchesByWindow();

            $historyStart = CarbonImmutable::today('UTC')
                ->subDays(self::HISTORY_DAYS);

            for (
                $dayIndex = 0;
                $dayIndex < self::HISTORY_DAYS;
                $dayIndex++
            ) {
                $productionDate = $historyStart->addDays(
                    $dayIndex
                );

                foreach ($machines as $machine) {
                    $line = $machine->productionLines->first();

                    if ($line === null) {
                        continue;
                    }

                    foreach ($shifts as $shift) {
                        $this->createShiftHistory(
                            productionDate: $productionDate,
                            machine: $machine,
                            line: $line,
                            shift: $shift,
                            batches: $batches,
                        );
                    }
                }
            }
        });
    }

    private function deleteExistingData(): void
    {
        ErpMaintenanceHistory::query()->delete();
        ErpMachineStatusEvent::query()->delete();
        ErpDowntimeEvent::query()->delete();
    }

    private function productionBatchesByWindow(): Collection
    {
        return ErpProductionBatch::query()
            ->with('productionOrder')
            ->get()
            ->keyBy(
                function (ErpProductionBatch $batch): string {
                    return implode('-', [
                        $batch->productionOrder
                            ->production_line_id,

                        $batch->shift_id,

                        $batch->scheduled_start_at
                            ->format('Y-m-d'),
                    ]);
                }
            );
    }

    private function createShiftHistory(
        CarbonImmutable $productionDate,
        ErpMachine $machine,
        $line,
        ErpShift $shift,
        Collection $batches,
    ): void {
        [$shiftStart, $shiftEnd] = $this->shiftWindow(
            productionDate: $productionDate,
            shift: $shift,
        );

        $batchKey = implode('-', [
            $line->id,
            $shift->id,
            $productionDate->format('Y-m-d'),
        ]);

        $batch = $batches->get($batchKey);

        $downtimeProbability = match (
            $machine->criticality
        ) {
            'critical' => 11,
            'high' => 8,
            'medium' => 5,
            default => 4,
        };

        $hasDowntime = mt_rand(1, 100)
            <= $downtimeProbability;

        if (!$hasDowntime) {
            $this->createStatusEvent(
                machine: $machine,
                lineId: $line->id,
                shiftId: $shift->id,
                statusCode: 'running',
                startedAt: $shiftStart,
                endedAt: $shiftEnd,
                sequence: 1,
            );

            return;
        }

        $downtimeType = $this->randomDowntimeType();

        $durationMinutes = $this->durationForType(
            $downtimeType
        );

        $downtimeStart = $shiftStart->addMinutes(
            mt_rand(30, 300)
        );

        $downtimeEnd = $downtimeStart->addMinutes(
            $durationMinutes
        );

        if ($downtimeEnd->greaterThan($shiftEnd)) {
            $downtimeEnd = $shiftEnd;

            $durationMinutes = $downtimeStart
                ->diffInMinutes($downtimeEnd);
        }

        $this->createStatusEvent(
            machine: $machine,
            lineId: $line->id,
            shiftId: $shift->id,
            statusCode: 'running',
            startedAt: $shiftStart,
            endedAt: $downtimeStart,
            sequence: 1,
        );

        $this->createStatusEvent(
            machine: $machine,
            lineId: $line->id,
            shiftId: $shift->id,
            statusCode: $this->statusForDowntime(
                $downtimeType
            ),
            startedAt: $downtimeStart,
            endedAt: $downtimeEnd,
            sequence: 2,
        );

        $this->createStatusEvent(
            machine: $machine,
            lineId: $line->id,
            shiftId: $shift->id,
            statusCode: 'running',
            startedAt: $downtimeEnd,
            endedAt: $shiftEnd,
            sequence: 3,
        );

        $isLateArrival = mt_rand(1, 100) <= 5;

        $sourceUpdatedAt = $isLateArrival
            ? $downtimeEnd->addDays(mt_rand(1, 3))
            : $downtimeEnd->addMinutes(mt_rand(2, 30));

        $impactFactor = mt_rand(65, 100) / 100;

        $productionImpact = (int) round(
            $line->nominal_capacity_units_per_hour
            * ($durationMinutes / 60)
            * $impactFactor
        );

        $eventNumber = sprintf(
            'DT-%s-%s-%s',
            $productionDate->format('Ymd'),
            $machine->code,
            $shift->code
        );

        $downtimeEvent = ErpDowntimeEvent::query()->create([
            'machine_id' => $machine->id,
            'production_line_id' => $line->id,
            'production_batch_id' => $batch?->id,
            'shift_id' => $shift->id,
            'event_number' => $eventNumber,
            'category' => $this->categoryForType(
                $downtimeType
            ),
            'downtime_type' => $downtimeType,
            'reason_code' => $this->reasonCodeForType(
                $downtimeType
            ),
            'reason_description' =>
                $this->descriptionForType(
                    $downtimeType
                ),
            'started_at' => $downtimeStart,
            'ended_at' => $downtimeEnd,
            'duration_minutes' => $durationMinutes,
            'production_impact_units' =>
                $productionImpact,
            'status' => 'resolved',
            'is_late_arrival' => $isLateArrival,
            'source_updated_at' => $sourceUpdatedAt,
        ]);

        if (
            in_array(
                $downtimeType,
                ['breakdown', 'planned_maintenance'],
                true
            )
        ) {
            $this->createMaintenanceRecord(
                downtimeEvent: $downtimeEvent,
                machine: $machine,
                lineId: $line->id,
                downtimeType: $downtimeType,
                startedAt: $downtimeStart,
                completedAt: $downtimeEnd,
                sourceUpdatedAt: $sourceUpdatedAt,
                isLateArrival: $isLateArrival,
            );
        }
    }

    private function createStatusEvent(
        ErpMachine $machine,
        int $lineId,
        int $shiftId,
        string $statusCode,
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
        int $sequence,
    ): void {
        $durationMinutes = $startedAt->diffInMinutes(
            $endedAt
        );

        if ($durationMinutes <= 0) {
            return;
        }

        $isLateArrival = mt_rand(1, 100) <= 3;

        $sourceUpdatedAt = $isLateArrival
            ? $endedAt->addDays(mt_rand(1, 2))
            : $endedAt->addMinutes(mt_rand(0, 15));

        $eventNumber = sprintf(
            'STS-%s-%s-%02d',
            $startedAt->format('YmdHis'),
            $machine->code,
            $sequence
        );

        ErpMachineStatusEvent::query()->create([
            'machine_id' => $machine->id,
            'production_line_id' => $lineId,
            'shift_id' => $shiftId,
            'status_event_number' => $eventNumber,
            'status_code' => $statusCode,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
            'is_late_arrival' => $isLateArrival,
            'source_updated_at' => $sourceUpdatedAt,
            'notes' =>
                'Synthetic machine status generated by the ERP simulator.',
        ]);
    }

    private function createMaintenanceRecord(
        ErpDowntimeEvent $downtimeEvent,
        ErpMachine $machine,
        int $lineId,
        string $downtimeType,
        CarbonImmutable $startedAt,
        CarbonImmutable $completedAt,
        CarbonImmutable $sourceUpdatedAt,
        bool $isLateArrival,
    ): void {
        $maintenanceType = $downtimeType === 'breakdown'
            ? 'corrective'
            : 'preventive';

        $repairDuration = $startedAt->diffInMinutes(
            $completedAt
        );

        $partsCost = $maintenanceType === 'corrective'
            ? mt_rand(0, 450000) / 100
            : mt_rand(0, 150000) / 100;

        $laborCost = round(
            ($repairDuration / 60) * 180,
            2
        );

        $maintenanceNumber = sprintf(
            'MNT-%s-%s',
            $startedAt->format('YmdHis'),
            $machine->code
        );

        ErpMaintenanceHistory::query()->create([
            'machine_id' => $machine->id,
            'production_line_id' => $lineId,
            'downtime_event_id' => $downtimeEvent->id,
            'maintenance_number' => $maintenanceNumber,
            'maintenance_type' => $maintenanceType,
            'priority' => $this->priorityForMachine(
                $machine
            ),
            'status' => 'completed',
            'reported_at' => $startedAt->subMinutes(
                mt_rand(0, 10)
            ),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'repair_duration_minutes' =>
                $repairDuration,
            'failure_code' => $maintenanceType
                === 'corrective'
                    ? $this->failureCodeForMachine(
                        $machine
                    )
                    : null,
            'failure_description' =>
                $maintenanceType === 'corrective'
                    ? 'Synthetic equipment failure.'
                    : null,
            'root_cause' =>
                $maintenanceType === 'corrective'
                    ? 'Simulated mechanical or electrical degradation.'
                    : 'Planned preventive-maintenance interval.',
            'actions_taken' =>
                $maintenanceType === 'corrective'
                    ? 'Inspection, repair, adjustment, and restart testing.'
                    : 'Inspection, cleaning, lubrication, and adjustment.',
            'technician_name' => sprintf(
                'Simulated Technician %d',
                mt_rand(1, 6)
            ),
            'parts_cost' => $partsCost,
            'labor_cost' => $laborCost,
            'total_cost' => round(
                $partsCost + $laborCost,
                2
            ),
            'currency_code' => 'MAD',
            'is_late_arrival' => $isLateArrival,
            'source_updated_at' => $sourceUpdatedAt,
        ]);
    }

    private function shiftWindow(
        CarbonImmutable $productionDate,
        ErpShift $shift,
    ): array {
        $endDate = $shift->crosses_midnight
            ? $productionDate->addDay()
            : $productionDate;

        return [
            CarbonImmutable::parse(
                $productionDate->format('Y-m-d')
                . ' '
                . $shift->start_time,
                'UTC'
            ),

            CarbonImmutable::parse(
                $endDate->format('Y-m-d')
                . ' '
                . $shift->end_time,
                'UTC'
            ),
        ];
    }

    private function randomDowntimeType(): string
    {
        $value = mt_rand(1, 100);

        return match (true) {
            $value <= 45 => 'breakdown',
            $value <= 60 => 'planned_maintenance',
            $value <= 72 => 'cleaning',
            $value <= 83 => 'changeover',
            $value <= 91 => 'material_shortage',
            $value <= 96 => 'quality_hold',
            default => 'utility_failure',
        };
    }

    private function durationForType(string $type): int
    {
        return match ($type) {
            'breakdown' => mt_rand(25, 120),
            'planned_maintenance' => mt_rand(30, 90),
            'cleaning' => mt_rand(15, 45),
            'changeover' => mt_rand(20, 50),
            'material_shortage' => mt_rand(10, 60),
            'quality_hold' => mt_rand(15, 75),
            'utility_failure' => mt_rand(10, 90),
            default => mt_rand(10, 45),
        };
    }

    private function statusForDowntime(string $type): string
    {
        return match ($type) {
            'breakdown' => 'stopped',
            'planned_maintenance' => 'maintenance',
            'cleaning' => 'cleaning',
            'changeover' => 'setup',
            'material_shortage' => 'idle',
            'quality_hold' => 'idle',
            'utility_failure' => 'stopped',
            default => 'stopped',
        };
    }

    private function categoryForType(string $type): string
    {
        return in_array(
            $type,
            [
                'planned_maintenance',
                'cleaning',
                'changeover',
            ],
            true
        )
            ? 'planned'
            : 'unplanned';
    }

    private function reasonCodeForType(string $type): string
    {
        return match ($type) {
            'breakdown' => 'BRK-001',
            'planned_maintenance' => 'PM-001',
            'cleaning' => 'CLN-001',
            'changeover' => 'CHG-001',
            'material_shortage' => 'MAT-001',
            'quality_hold' => 'QLT-001',
            'utility_failure' => 'UTL-001',
            default => 'OTH-001',
        };
    }

    private function descriptionForType(string $type): string
    {
        return match ($type) {
            'breakdown' =>
                'Synthetic unexpected machine breakdown.',

            'planned_maintenance' =>
                'Synthetic planned preventive maintenance.',

            'cleaning' =>
                'Synthetic cleaning and sanitation stop.',

            'changeover' =>
                'Synthetic product or packaging changeover.',

            'material_shortage' =>
                'Synthetic raw-material or packaging shortage.',

            'quality_hold' =>
                'Synthetic quality-control production hold.',

            'utility_failure' =>
                'Synthetic electrical, air, or water interruption.',

            default =>
                'Synthetic production downtime event.',
        };
    }

    private function priorityForMachine(
        ErpMachine $machine
    ): string {
        return match ($machine->criticality) {
            'critical' => 'urgent',
            'high' => 'high',
            'medium' => 'normal',
            default => 'low',
        };
    }

    private function failureCodeForMachine(
        ErpMachine $machine
    ): string {
        return match ($machine->machine_type) {
            'mixing_tank' => 'MIX-FAILURE',
            'homogenizer' => 'HOM-PRESSURE',
            'sterilizer' => 'STR-TEMPERATURE',
            'filling_machine' => 'FIL-JAM',
            'closure_applicator' => 'CLS-ALIGNMENT',
            'cartoning_machine' => 'CRT-JAM',
            'palletizer' => 'PAL-SENSOR',
            default => 'GEN-FAILURE',
        };
    }
}