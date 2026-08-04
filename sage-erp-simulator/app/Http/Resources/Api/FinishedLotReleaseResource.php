<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinishedLotReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'release_number' => $this->release_number,
            'lot_number' => $this->lot_number,
            'decision' => $this->decision,

            'warehouse_status' =>
                $this->warehouse_status,

            'decision_at' =>
                $this->decision_at?->toIso8601String(),

            'released_at' =>
                $this->released_at?->toIso8601String(),

            'released_by' => $this->released_by,

            'quality_certificate_number' =>
                $this->quality_certificate_number,

            'approved_quantity' =>
                $this->approved_quantity,

            'blocked_quantity' =>
                $this->blocked_quantity,

            'rejected_quantity' =>
                $this->rejected_quantity,

            'expiry_date' =>
                $this->expiry_date?->format('Y-m-d'),

            'decision_reason' => $this->decision_reason,

            'is_late_arrival' => $this->is_late_arrival,

            'source_updated_at' =>
                $this->source_updated_at
                    ?->toIso8601String(),

            'production_batch' => $this->whenLoaded(
                'productionBatch',
                fn (): array => [
                    'external_id' =>
                        $this->productionBatch
                            ->external_id,

                    'batch_number' =>
                        $this->productionBatch
                            ->batch_number,

                    'lot_number' =>
                        $this->productionBatch
                            ->lot_number,

                    'gross_quantity' =>
                        $this->productionBatch
                            ->gross_quantity,

                    'good_quantity' =>
                        $this->productionBatch
                            ->good_quantity,

                    'rejected_quantity' =>
                        $this->productionBatch
                            ->rejected_quantity,

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

            'quality_inspection' => $this->whenLoaded(
                'qualityInspection',
                fn (): array => [
                    'external_id' =>
                        $this->qualityInspection
                            ->external_id,

                    'inspection_number' =>
                        $this->qualityInspection
                            ->inspection_number,

                    'result' =>
                        $this->qualityInspection->result,

                    'overall_score_percent' =>
                        $this->qualityInspection
                            ->overall_score_percent,

                    'nonconformity_code' =>
                        $this->qualityInspection
                            ->nonconformity_code,
                ]
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}