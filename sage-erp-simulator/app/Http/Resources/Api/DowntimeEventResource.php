<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DowntimeEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'event_number' => $this->event_number,
            'category' => $this->category,
            'downtime_type' => $this->downtime_type,
            'reason_code' => $this->reason_code,
            'reason_description' =>
                $this->reason_description,

            'started_at' =>
                $this->started_at?->toIso8601String(),

            'ended_at' =>
                $this->ended_at?->toIso8601String(),

            'duration_minutes' => $this->duration_minutes,

            'production_impact_units' =>
                $this->production_impact_units,

            'status' => $this->status,
            'is_late_arrival' => $this->is_late_arrival,

            'source_updated_at' =>
                $this->source_updated_at?->toIso8601String(),

            'machine' => $this->whenLoaded(
                'machine',
                fn (): array => [
                    'external_id' =>
                        $this->machine->external_id,

                    'code' => $this->machine->code,
                    'name' => $this->machine->name,

                    'machine_type' =>
                        $this->machine->machine_type,

                    'criticality' =>
                        $this->machine->criticality,
                ]
            ),

            'production_line' => $this->whenLoaded(
                'productionLine',
                fn (): array => [
                    'external_id' =>
                        $this->productionLine->external_id,

                    'code' => $this->productionLine->code,
                    'name' => $this->productionLine->name,
                ]
            ),

            'shift' => $this->whenLoaded(
                'shift',
                fn (): ?array => $this->shift === null
                    ? null
                    : [
                        'external_id' =>
                            $this->shift->external_id,

                        'code' => $this->shift->code,
                        'name' => $this->shift->name,
                    ]
            ),

            'production_batch' => $this->whenLoaded(
                'productionBatch',
                function (): ?array {
                    if ($this->productionBatch === null) {
                        return null;
                    }

                    return [
                        'external_id' =>
                            $this->productionBatch
                                ->external_id,

                        'batch_number' =>
                            $this->productionBatch
                                ->batch_number,

                        'lot_number' =>
                            $this->productionBatch
                                ->lot_number,

                        'production_order' => [
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
                    ];
                }
            ),

            'maintenance_record' => $this->whenLoaded(
                'maintenanceRecord',
                fn (): ?array =>
                    $this->maintenanceRecord === null
                        ? null
                        : [
                            'external_id' =>
                                $this->maintenanceRecord
                                    ->external_id,

                            'maintenance_number' =>
                                $this->maintenanceRecord
                                    ->maintenance_number,

                            'maintenance_type' =>
                                $this->maintenanceRecord
                                    ->maintenance_type,

                            'status' =>
                                $this->maintenanceRecord
                                    ->status,
                        ]
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}