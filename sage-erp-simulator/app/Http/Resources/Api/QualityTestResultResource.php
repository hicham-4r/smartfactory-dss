<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QualityTestResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'test_code' => $this->test_code,
            'test_name' => $this->test_name,
            'test_category' => $this->test_category,

            'numeric_value' => $this->numeric_value,
            'text_value' => $this->text_value,
            'unit' => $this->unit,

            'minimum_specification' =>
                $this->minimum_specification,

            'maximum_specification' =>
                $this->maximum_specification,

            'result' => $this->result,

            'tested_at' =>
                $this->tested_at?->toIso8601String(),

            'notes' => $this->notes,

            'quality_inspection' => $this->whenLoaded(
                'qualityInspection',
                fn (): array => [
                    'external_id' =>
                        $this->qualityInspection
                            ->external_id,

                    'inspection_number' =>
                        $this->qualityInspection
                            ->inspection_number,

                    'inspection_type' =>
                        $this->qualityInspection
                            ->inspection_type,

                    'inspection_result' =>
                        $this->qualityInspection->result,

                    'overall_score_percent' =>
                        $this->qualityInspection
                            ->overall_score_percent,

                    'source_updated_at' =>
                        $this->qualityInspection
                            ->source_updated_at
                            ?->toIso8601String(),

                    'product' => [
                        'external_id' =>
                            $this->qualityInspection
                                ->product
                                ->external_id,

                        'code' =>
                            $this->qualityInspection
                                ->product
                                ->code,

                        'name' =>
                            $this->qualityInspection
                                ->product
                                ->name,
                    ],

                    'production_line' => [
                        'external_id' =>
                            $this->qualityInspection
                                ->productionLine
                                ->external_id,

                        'code' =>
                            $this->qualityInspection
                                ->productionLine
                                ->code,

                        'name' =>
                            $this->qualityInspection
                                ->productionLine
                                ->name,
                    ],

                    'shift' => [
                        'external_id' =>
                            $this->qualityInspection
                                ->shift
                                ->external_id,

                        'code' =>
                            $this->qualityInspection
                                ->shift
                                ->code,

                        'name' =>
                            $this->qualityInspection
                                ->shift
                                ->name,
                    ],

                    'production_batch' => [
                        'external_id' =>
                            $this->qualityInspection
                                ->productionBatch
                                ->external_id,

                        'batch_number' =>
                            $this->qualityInspection
                                ->productionBatch
                                ->batch_number,

                        'lot_number' =>
                            $this->qualityInspection
                                ->productionBatch
                                ->lot_number,
                    ],
                ]
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}