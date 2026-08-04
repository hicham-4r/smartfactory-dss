<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'record_number' => $this->record_number,

            'interval_start_at' =>
                $this->interval_start_at?->toIso8601String(),

            'interval_end_at' =>
                $this->interval_end_at?->toIso8601String(),

            'recorded_at' =>
                $this->recorded_at?->toIso8601String(),

            'target_quantity' => $this->target_quantity,
            'gross_quantity' => $this->gross_quantity,
            'good_quantity' => $this->good_quantity,
            'rejected_quantity' => $this->rejected_quantity,

            'runtime_minutes' => $this->runtime_minutes,
            'downtime_minutes' => $this->downtime_minutes,

            'quality_rate_percent' =>
                $this->quality_rate_percent,

            'is_late_arrival' => $this->is_late_arrival,

            'source_updated_at' =>
                $this->source_updated_at?->toIso8601String(),

            'notes' => $this->notes,

            'batch' => $this->whenLoaded(
                'productionBatch',
                fn (): array => [
                    'external_id' =>
                        $this->productionBatch->external_id,

                    'batch_number' =>
                        $this->productionBatch->batch_number,

                    'lot_number' =>
                        $this->productionBatch->lot_number,

                    'quality_status' =>
                        $this->productionBatch->quality_status,

                    'order' => [
                        'external_id' =>
                            $this->productionBatch
                                ->productionOrder
                                ->external_id,

                        'order_number' =>
                            $this->productionBatch
                                ->productionOrder
                                ->order_number,
                    ],

                    'product' => [
                        'external_id' =>
                            $this->productionBatch
                                ->productionOrder
                                ->product
                                ->external_id,

                        'code' =>
                            $this->productionBatch
                                ->productionOrder
                                ->product
                                ->code,

                        'name' =>
                            $this->productionBatch
                                ->productionOrder
                                ->product
                                ->name,
                    ],

                    'production_line' => [
                        'external_id' =>
                            $this->productionBatch
                                ->productionOrder
                                ->productionLine
                                ->external_id,

                        'code' =>
                            $this->productionBatch
                                ->productionOrder
                                ->productionLine
                                ->code,

                        'name' =>
                            $this->productionBatch
                                ->productionOrder
                                ->productionLine
                                ->name,
                    ],

                    'shift' => [
                        'external_id' =>
                            $this->productionBatch
                                ->shift
                                ->external_id,

                        'code' =>
                            $this->productionBatch
                                ->shift
                                ->code,

                        'name' =>
                            $this->productionBatch
                                ->shift
                                ->name,
                    ],
                ]
            ),

            'machine' => $this->whenLoaded(
                'machine',
                fn (): ?array => $this->machine === null
                    ? null
                    : [
                        'external_id' =>
                            $this->machine->external_id,

                        'code' => $this->machine->code,
                        'name' => $this->machine->name,
                        'machine_type' =>
                            $this->machine->machine_type,
                    ]
            ),

            'process_stage' => $this->whenLoaded(
                'processStage',
                fn (): ?array => $this->processStage === null
                    ? null
                    : [
                        'external_id' =>
                            $this->processStage->external_id,

                        'code' => $this->processStage->code,
                        'name' => $this->processStage->name,
                    ]
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}