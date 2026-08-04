<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionLineResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->status,

            'nominal_capacity_units_per_hour' =>
                $this->nominal_capacity_units_per_hour,

            'is_active' => $this->is_active,

            'machines_count' =>
                $this->whenCounted('machines'),

            'products_count' =>
                $this->whenCounted('products'),

            'machines' => $this->whenLoaded(
                'machines',
                fn () => $this->machines->map(
                    fn ($machine): array => [
                        'external_id' => $machine->external_id,
                        'code' => $machine->code,
                        'name' => $machine->name,
                        'machine_type' => $machine->machine_type,
                        'status' => $machine->status,

                        'sequence_order' =>
                            $machine->pivot->sequence_order,

                        'station_code' =>
                            $machine->pivot->station_code,
                    ]
                )
            ),

            'products' => $this->whenLoaded(
                'products',
                fn () => $this->products->map(
                    fn ($product): array => [
                        'external_id' => $product->external_id,
                        'code' => $product->code,
                        'name' => $product->name,
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