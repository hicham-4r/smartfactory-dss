<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MachineStatusEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,

            'status_event_number' =>
                $this->status_event_number,

            'status_code' => $this->status_code,

            'started_at' =>
                $this->started_at?->toIso8601String(),

            'ended_at' =>
                $this->ended_at?->toIso8601String(),

            'duration_minutes' => $this->duration_minutes,
            'is_late_arrival' => $this->is_late_arrival,

            'source_updated_at' =>
                $this->source_updated_at?->toIso8601String(),

            'notes' => $this->notes,

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

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}