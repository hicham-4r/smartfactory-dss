<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'batch_number' => $this->batch_number,
            'lot_number' => $this->lot_number,

            'scheduled_start_at' =>
                $this->scheduled_start_at?->toIso8601String(),

            'scheduled_end_at' =>
                $this->scheduled_end_at?->toIso8601String(),

            'actual_start_at' =>
                $this->actual_start_at?->toIso8601String(),

            'actual_end_at' =>
                $this->actual_end_at?->toIso8601String(),

            'planned_quantity' => $this->planned_quantity,
            'gross_quantity' => $this->gross_quantity,
            'good_quantity' => $this->good_quantity,
            'rejected_quantity' => $this->rejected_quantity,

            'status' => $this->status,
            'quality_status' => $this->quality_status,

            'expiry_date' =>
                $this->expiry_date?->format('Y-m-d'),

            'records_count' =>
                $this->whenCounted('records'),

            'production_order' => $this->whenLoaded(
                'productionOrder',
                fn (): array => [
                    'external_id' =>
                        $this->productionOrder->external_id,

                    'order_number' =>
                        $this->productionOrder->order_number,

                    'product' => [
                        'external_id' =>
                            $this->productionOrder
                                ->product
                                ->external_id,

                        'code' =>
                            $this->productionOrder
                                ->product
                                ->code,

                        'name' =>
                            $this->productionOrder
                                ->product
                                ->name,
                    ],

                    'production_line' => [
                        'external_id' =>
                            $this->productionOrder
                                ->productionLine
                                ->external_id,

                        'code' =>
                            $this->productionOrder
                                ->productionLine
                                ->code,

                        'name' =>
                            $this->productionOrder
                                ->productionLine
                                ->name,
                    ],
                ]
            ),

            'shift' => $this->whenLoaded(
                'shift',
                fn (): array => [
                    'external_id' => $this->shift->external_id,
                    'code' => $this->shift->code,
                    'name' => $this->shift->name,
                ]
            ),

            'operator_assignment' => $this->whenLoaded(
                'operatorAssignment',
                function (): ?array {
                    if ($this->operatorAssignment === null) {
                        return null;
                    }

                    return [
                        'role_on_line' =>
                            $this->operatorAssignment
                                ->role_on_line,

                        'operator' => [
                            'external_id' =>
                                $this->operatorAssignment
                                    ->operator
                                    ->external_id,

                            'employee_code' =>
                                $this->operatorAssignment
                                    ->operator
                                    ->employee_code,

                            'full_name' =>
                                $this->operatorAssignment
                                    ->operator
                                    ->full_name,
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