<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MachineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'code' => $this->code,
            'name' => $this->name,
            'machine_type' => $this->machine_type,
            'manufacturer' => $this->manufacturer,
            'model_reference' => $this->model_reference,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'criticality' => $this->criticality,

            'installation_date' =>
                $this->installation_date?->format('Y-m-d'),

            'is_active' => $this->is_active,

            'production_lines' => $this->whenLoaded(
                'productionLines',
                fn () => $this->productionLines->map(
                    fn ($line): array => [
                        'external_id' => $line->external_id,
                        'code' => $line->code,
                        'name' => $line->name,

                        'sequence_order' =>
                            $line->pivot->sequence_order,

                        'station_code' =>
                            $line->pivot->station_code,

                        'is_primary' =>
                            (bool) $line->pivot->is_primary,
                    ]
                )
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}