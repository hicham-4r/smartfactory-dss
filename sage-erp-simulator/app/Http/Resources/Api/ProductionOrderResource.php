<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'order_number' => $this->order_number,

            'planned_start_at' =>
                $this->planned_start_at?->toIso8601String(),

            'planned_end_at' =>
                $this->planned_end_at?->toIso8601String(),

            'planned_quantity' => $this->planned_quantity,
            'priority' => $this->priority,
            'status' => $this->status,
            'notes' => $this->notes,

            'batches_count' =>
                $this->whenCounted('batches'),

            'product' => $this->whenLoaded(
                'product',
                fn (): array => [
                    'external_id' => $this->product->external_id,
                    'code' => $this->product->code,
                    'name' => $this->product->name,
                    'flavor' => $this->product->flavor,
                    'beverage_type' =>
                        $this->product->beverage_type,

                    'family' => [
                        'code' => $this->product->family->code,
                        'name' => $this->product->family->name,
                    ],

                    'packaging_format' => [
                        'code' =>
                            $this->product
                                ->packagingFormat
                                ->code,

                        'label' =>
                            $this->product
                                ->packagingFormat
                                ->label,

                        'volume_ml' =>
                            $this->product
                                ->packagingFormat
                                ->volume_ml,
                    ],
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

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}