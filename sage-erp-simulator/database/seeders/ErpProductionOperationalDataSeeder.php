<?php

namespace Database\Seeders;

use App\Models\ErpMachine;
use App\Models\ErpOperatorAssignment;
use App\Models\ErpProcessStage;
use App\Models\ErpProductionBatch;
use App\Models\ErpProductionLine;
use App\Models\ErpProductionOrder;
use App\Models\ErpProductionRecord;
use App\Models\ErpShift;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ErpProductionOperationalDataSeeder extends Seeder
{
    private const HISTORY_DAYS = 90;

    private const RECORDS_PER_BATCH = 4;

    public function run(): void
    {
        DB::transaction(function (): void {
            mt_srand(20260724);

            $this->deleteExistingOperationalData();

            $lines = ErpProductionLine::query()
                ->with('products')
                ->orderBy('id')
                ->get()
                ->values();

            $shifts = ErpShift::query()
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get()
                ->values();

            if ($lines->isEmpty() || $shifts->isEmpty()) {
                throw new RuntimeException(
                    'Production lines and shifts must be seeded first.'
                );
            }

            $fillingStage = ErpProcessStage::query()
                ->where('code', 'FILLING')
                ->firstOrFail();

            $primaryAssignments = ErpOperatorAssignment::query()
                ->where('is_primary', true)
                ->where('is_active', true)
                ->get()
                ->keyBy(
                    fn (ErpOperatorAssignment $assignment): string =>
                        $assignment->production_line_id
                        . '-'
                        . $assignment->shift_id
                );

            $fillingMachines = $this->fillingMachinesByLine();

            $historyStart = CarbonImmutable::today('UTC')
                ->subDays(self::HISTORY_DAYS);

            for (
                $dayIndex = 0;
                $dayIndex < self::HISTORY_DAYS;
                $dayIndex++
            ) {
                $productionDate = $historyStart->addDays($dayIndex);

                foreach ($lines as $lineIndex => $line) {
                    $this->createDailyOrder(
                        productionDate: $productionDate,
                        dayIndex: $dayIndex,
                        lineIndex: $lineIndex,
                        line: $line,
                        shifts: $shifts,
                        primaryAssignments: $primaryAssignments,
                        fillingMachine: $fillingMachines->get($line->id),
                        fillingStageId: $fillingStage->id,
                    );
                }
            }
        });
    }

    private function deleteExistingOperationalData(): void
    {
        ErpProductionRecord::query()->delete();
        ErpProductionBatch::query()->delete();
        ErpProductionOrder::query()->delete();
    }

    private function fillingMachinesByLine(): Collection
    {
        $machines = ErpMachine::query()
            ->with('productionLines')
            ->where('machine_type', 'filling_machine')
            ->get();

        return $machines
            ->flatMap(function (ErpMachine $machine): array {
                return $machine->productionLines
                    ->mapWithKeys(
                        fn ($line): array => [
                            $line->id => $machine,
                        ]
                    )
                    ->all();
            });
    }

    private function createDailyOrder(
        CarbonImmutable $productionDate,
        int $dayIndex,
        int $lineIndex,
        ErpProductionLine $line,
        Collection $shifts,
        Collection $primaryAssignments,
        ?ErpMachine $fillingMachine,
        int $fillingStageId,
    ): void {
        $products = $line->products->values();

        if ($products->isEmpty()) {
            throw new RuntimeException(
                "No products assigned to production line {$line->code}."
            );
        }

        $productPosition = (
            $dayIndex + $lineIndex
        ) % $products->count();

        $product = $products->get($productPosition);

        $batchPlans = $this->buildBatchPlans(
            productionDate: $productionDate,
            line: $line,
            shifts: $shifts,
        );

        $plannedOrderQuantity = collect($batchPlans)
            ->sum('planned_quantity');

        $orderNumber = sprintf(
            'PO-%s-%s',
            $productionDate->format('Ymd'),
            $line->code
        );

        $productionOrder = ErpProductionOrder::query()->create([
            'product_id' => $product->id,
            'production_line_id' => $line->id,
            'order_number' => $orderNumber,
            'planned_start_at' =>
                $batchPlans[0]['scheduled_start_at'],
            'planned_end_at' =>
                $batchPlans[array_key_last($batchPlans)]
                    ['scheduled_end_at'],
            'planned_quantity' => $plannedOrderQuantity,
            'priority' => mt_rand(2, 4),
            'status' => 'completed',
            'notes' =>
                'Synthetic production order generated by the ERP simulator.',
        ]);

        foreach ($batchPlans as $batchPlan) {
            $shift = $batchPlan['shift'];

            $assignmentKey = $line->id . '-' . $shift->id;

            $assignment = $primaryAssignments->get(
                $assignmentKey
            );

            $this->createBatch(
                productionOrder: $productionOrder,
                product: $product,
                line: $line,
                shift: $shift,
                operatorAssignment: $assignment,
                fillingMachine: $fillingMachine,
                fillingStageId: $fillingStageId,
                scheduledStart: $batchPlan['scheduled_start_at'],
                scheduledEnd: $batchPlan['scheduled_end_at'],
                plannedQuantity: $batchPlan['planned_quantity'],
            );
        }
    }

    private function buildBatchPlans(
        CarbonImmutable $productionDate,
        ErpProductionLine $line,
        Collection $shifts,
    ): array {
        $plans = [];

        foreach ($shifts as $shift) {
            [$scheduledStart, $scheduledEnd] =
                $this->shiftWindow(
                    productionDate: $productionDate,
                    shift: $shift,
                );

            $utilizationRate = mt_rand(72, 88) / 100;

            $plannedQuantity = (int) round(
                $line->nominal_capacity_units_per_hour
                * 8
                * $utilizationRate
            );

            $plans[] = [
                'shift' => $shift,
                'scheduled_start_at' => $scheduledStart,
                'scheduled_end_at' => $scheduledEnd,
                'planned_quantity' => $plannedQuantity,
            ];
        }

        return $plans;
    }

    private function shiftWindow(
        CarbonImmutable $productionDate,
        ErpShift $shift,
    ): array {
        $startDate = $productionDate;

        $endDate = $shift->crosses_midnight
            ? $productionDate->addDay()
            : $productionDate;

        $scheduledStart = CarbonImmutable::parse(
            $startDate->format('Y-m-d')
            . ' '
            . $shift->start_time,
            'UTC'
        );

        $scheduledEnd = CarbonImmutable::parse(
            $endDate->format('Y-m-d')
            . ' '
            . $shift->end_time,
            'UTC'
        );

        return [$scheduledStart, $scheduledEnd];
    }

    private function createBatch(
        ErpProductionOrder $productionOrder,
        $product,
        ErpProductionLine $line,
        ErpShift $shift,
        ?ErpOperatorAssignment $operatorAssignment,
        ?ErpMachine $fillingMachine,
        int $fillingStageId,
        CarbonImmutable $scheduledStart,
        CarbonImmutable $scheduledEnd,
        int $plannedQuantity,
    ): void {
        $batchDate = $scheduledStart->format('Ymd');

        $batchNumber = sprintf(
            'BATCH-%s-%s-%s',
            $batchDate,
            $line->code,
            $shift->code
        );

        $lotNumber = sprintf(
            'LOT-%s-%s-%s',
            $batchDate,
            $line->id,
            $shift->id
        );

        $actualStart = $scheduledStart->addMinutes(
            mt_rand(-5, 8)
        );

        $actualEnd = $scheduledEnd->addMinutes(
            mt_rand(-8, 20)
        );

        $batch = ErpProductionBatch::query()->create([
            'production_order_id' => $productionOrder->id,
            'shift_id' => $shift->id,
            'operator_assignment_id' =>
                $operatorAssignment?->id,
            'batch_number' => $batchNumber,
            'lot_number' => $lotNumber,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'actual_start_at' => $actualStart,
            'actual_end_at' => $actualEnd,
            'planned_quantity' => $plannedQuantity,
            'gross_quantity' => 0,
            'good_quantity' => 0,
            'rejected_quantity' => 0,
            'status' => 'completed',
            'quality_status' => 'pending',
            'expiry_date' => $scheduledStart
                ->addDays($product->shelf_life_days ?? 240)
                ->toDateString(),
        ]);

        $totals = $this->createProductionRecords(
            batch: $batch,
            line: $line,
            shift: $shift,
            fillingMachine: $fillingMachine,
            fillingStageId: $fillingStageId,
            scheduledStart: $scheduledStart,
            plannedQuantity: $plannedQuantity,
        );

        $rejectionRate = $totals['gross_quantity'] > 0
            ? $totals['rejected_quantity']
                / $totals['gross_quantity']
            : 0;

        $qualityStatus = (
            $rejectionRate > 0.03
            || $totals['good_quantity']
                < ($plannedQuantity * 0.85)
        )
            ? 'rejected'
            : 'approved';

        $batch->update([
            'gross_quantity' => $totals['gross_quantity'],
            'good_quantity' => $totals['good_quantity'],
            'rejected_quantity' =>
                $totals['rejected_quantity'],
            'quality_status' => $qualityStatus,
        ]);
    }

    private function createProductionRecords(
        ErpProductionBatch $batch,
        ErpProductionLine $line,
        ErpShift $shift,
        ?ErpMachine $fillingMachine,
        int $fillingStageId,
        CarbonImmutable $scheduledStart,
        int $plannedQuantity,
    ): array {
        $grossTotal = 0;
        $goodTotal = 0;
        $rejectedTotal = 0;
        $assignedTarget = 0;

        for (
            $recordIndex = 1;
            $recordIndex <= self::RECORDS_PER_BATCH;
            $recordIndex++
        ) {
            $intervalStart = $scheduledStart->addHours(
                ($recordIndex - 1) * 2
            );

            $intervalEnd = $intervalStart->addHours(2);

            $targetQuantity = $recordIndex
                < self::RECORDS_PER_BATCH
                ? intdiv(
                    $plannedQuantity,
                    self::RECORDS_PER_BATCH
                )
                : $plannedQuantity - $assignedTarget;

            $assignedTarget += $targetQuantity;

            $downtimeMinutes = mt_rand(0, 18);

            if (mt_rand(1, 100) <= 6) {
                $downtimeMinutes = mt_rand(25, 45);
            }

            $runtimeMinutes = max(
                0,
                120 - $downtimeMinutes
            );

            $performanceFactor = mt_rand(92, 104) / 100;

            $runtimeFactor = $runtimeMinutes / 120;

            $grossQuantity = (int) round(
                $targetQuantity
                * $performanceFactor
                * $runtimeFactor
            );

            $rejectRate = mt_rand(5, 25) / 1000;

            if (mt_rand(1, 100) <= 3) {
                $rejectRate = mt_rand(40, 70) / 1000;
            }

            $rejectedQuantity = (int) round(
                $grossQuantity * $rejectRate
            );

            $goodQuantity = max(
                0,
                $grossQuantity - $rejectedQuantity
            );

            $qualityRate = $grossQuantity > 0
                ? round(
                    ($goodQuantity / $grossQuantity) * 100,
                    2
                )
                : null;

            $recordedAt = $intervalEnd->addMinutes(
                mt_rand(2, 10)
            );

            $isLateArrival = mt_rand(1, 100) <= 4;

            $sourceUpdatedAt = $isLateArrival
                ? $recordedAt->addDays(mt_rand(1, 3))
                : $recordedAt->addMinutes(mt_rand(0, 20));

            $recordNumber = sprintf(
                'REC-%s-%s-%s-%02d',
                $scheduledStart->format('Ymd'),
                $line->code,
                $shift->code,
                $recordIndex
            );

            ErpProductionRecord::query()->create([
                'production_batch_id' => $batch->id,
                'machine_id' => $fillingMachine?->id,
                'process_stage_id' => $fillingStageId,
                'record_number' => $recordNumber,
                'interval_start_at' => $intervalStart,
                'interval_end_at' => $intervalEnd,
                'recorded_at' => $recordedAt,
                'target_quantity' => $targetQuantity,
                'gross_quantity' => $grossQuantity,
                'good_quantity' => $goodQuantity,
                'rejected_quantity' =>
                    $rejectedQuantity,
                'runtime_minutes' => $runtimeMinutes,
                'downtime_minutes' => $downtimeMinutes,
                'quality_rate_percent' => $qualityRate,
                'is_late_arrival' => $isLateArrival,
                'source_updated_at' => $sourceUpdatedAt,
                'notes' =>
                    'Synthetic interval record from the ERP simulator.',
            ]);

            $grossTotal += $grossQuantity;
            $goodTotal += $goodQuantity;
            $rejectedTotal += $rejectedQuantity;
        }

        return [
            'gross_quantity' => $grossTotal,
            'good_quantity' => $goodTotal,
            'rejected_quantity' => $rejectedTotal,
        ];
    }
}