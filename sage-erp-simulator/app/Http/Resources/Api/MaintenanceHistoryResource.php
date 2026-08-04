<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,

            'maintenance_number' =>
                $this->maintenance_number,

            'maintenance_type' =>
                $this->maintenance_type,

            'priority' => $this->priority,
            'status' => $this->status,

            'reported_at' =>
                $this->reported_at?->toIso8601String(),

            'started_at' =>
                $this->started_at?->toIso8601String(),

            'completed_at' =>
                $this->completed_at?->toIso8601String(),

            'repair_duration_minutes' =>
                $this->repair_duration_minutes,

            'failure_code' => $this->failure_code,

            'failure_description' =>
                $this->failure_description,

            'root_cause' => $this->root_cause,
            'actions_taken' => $this->actions_taken,
            'technician_name' => $this->technician_name,

            'costs' => [
                'parts_cost' => $this->parts_cost,
                'labor_cost' => $this->labor_cost,
                'total_cost' => $this->total_cost,
                'currency_code' => $this->currency_code,
            ],

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

            'downtime_event' => $this->whenLoaded(
                'downtimeEvent',
                function (): ?array {
                    if ($this->downtimeEvent === null) {
                        return null;
                    }

                    return [
                        'external_id' =>
                            $this->downtimeEvent
                                ->external_id,

                        'event_number' =>
                            $this->downtimeEvent
                                ->event_number,

                        'category' =>
                            $this->downtimeEvent->category,

                        'downtime_type' =>
                            $this->downtimeEvent
                                ->downtime_type,

                        'duration_minutes' =>
                            $this->downtimeEvent
                                ->duration_minutes,

                        'shift' =>
                            $this->downtimeEvent->shift === null
                                ? null
                                : [
                                    'external_id' =>
                                        $this->downtimeEvent
                                            ->shift
                                            ->external_id,

                                    'code' =>
                                        $this->downtimeEvent
                                            ->shift
                                            ->code,

                                    'name' =>
                                        $this->downtimeEvent
                                            ->shift
                                            ->name,
                                ],
                    ];
                }
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}