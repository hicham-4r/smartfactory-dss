<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'flavor' => $this->flavor,
            'beverage_type' => $this->beverage_type,
            'contains_milk' => $this->contains_milk,
            'shelf_life_days' => $this->shelf_life_days,
            'status' => $this->status,
            'is_active' => $this->is_active,

            /*
             * Canonical relationship identifier used by the DSS
             * synchronization mapper.
             */
            'product_family_external_id' =>
                $this->whenLoaded(
                    'family',
                    fn (): ?string =>
                        $this->family?->external_id
                ),

            'family' => $this->whenLoaded(
                'family',
                fn (): array => [
                    'external_id' => $this->family->external_id,
                    'code' => $this->family->code,
                    'name' => $this->family->name,
                ]
            ),

            'packaging_format' => $this->whenLoaded(
                'packagingFormat',
                fn (): array => [
                    'external_id' =>
                        $this->packagingFormat->external_id,

                    'code' => $this->packagingFormat->code,
                    'label' => $this->packagingFormat->label,

                    'volume_ml' =>
                        $this->packagingFormat->volume_ml,

                    'package_type' =>
                        $this->packagingFormat->package_type,

                    'closure_type' =>
                        $this->packagingFormat->closure_type,

                    'has_straw' =>
                        $this->packagingFormat->has_straw,
                ]
            ),

            'production_lines' => $this->whenLoaded(
                'productionLines',
                fn () => $this->productionLines->map(
                    fn ($line): array => [
                        'external_id' => $line->external_id,
                        'code' => $line->code,
                        'name' => $line->name,

                        'is_preferred' =>
                            (bool) $line->pivot->is_preferred,

                        'nominal_rate_units_per_hour' =>
                            $line->pivot
                                ->nominal_rate_units_per_hour,
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
