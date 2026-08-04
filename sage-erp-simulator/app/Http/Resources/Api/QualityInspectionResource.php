<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QualityInspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'inspection_number' => $this->inspection_number,
            'inspection_type' => $this->inspection_type,

            'sampled_at' =>
                $this->sampled_at?->toIso8601String(),

            'inspection_started_at' =>
                $this->inspection_started_at
                    ?->toIso8601String(),

            'inspection_completed_at' =>
                $this->inspection_completed_at
                    ?->toIso8601String(),

            'inspector_name' => $this->inspector_name,
            'status' => $this->status,
            'result' => $this->result,

            'overall_score_percent' =>
                $this->overall_score_percent,

            'nonconformity_code' =>
                $this->nonconformity_code,

            'nonconformity_description' =>
                $this->nonconformity_description,

            'corrective_action' =>
                $this->corrective_action,

            'is_late_arrival' => $this->is_late_arrival,

            'source_updated_at' =>
                $this->source_updated_at
                    ?->toIso8601String(),

            'test_results_count' =>
                $this->whenCounted('testResults'),

            'product' => $this->whenLoaded(
                'product',
                fn (): array => [
                    'external_id' =>
                        $this->product->external_id,

                    'code' => $this->product->code,
                    'name' => $this->product->name,
                    'flavor' => $this->product->flavor,

                    'family' => [
                        'code' =>
                            $this->product->family->code,

                        'name' =>
                            $this->product->family->name,
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

            'shift' => $this->whenLoaded(
                'shift',
                fn (): array => [
                    'external_id' =>
                        $this->shift->external_id,

                    'code' => $this->shift->code,
                    'name' => $this->shift->name,
                ]
            ),

            'production_batch' => $this->whenLoaded(
                'productionBatch',
                fn (): array => [
                    'external_id' =>
                        $this->productionBatch->external_id,

                    'batch_number' =>
                        $this->productionBatch->batch_number,

                    'lot_number' =>
                        $this->productionBatch->lot_number,

                    'quality_status' =>
                        $this->productionBatch
                            ->quality_status,

                    'gross_quantity' =>
                        $this->productionBatch
                            ->gross_quantity,

                    'good_quantity' =>
                        $this->productionBatch
                            ->good_quantity,

                    'rejected_quantity' =>
                        $this->productionBatch
                            ->rejected_quantity,
                ]
            ),

            'test_results' => $this->whenLoaded(
                'testResults',
                fn () => $this->testResults->map(
                    fn ($test): array => [
                        'test_code' => $test->test_code,
                        'test_name' => $test->test_name,

                        'test_category' =>
                            $test->test_category,

                        'numeric_value' =>
                            $test->numeric_value,

                        'text_value' =>
                            $test->text_value,

                        'unit' => $test->unit,

                        'minimum_specification' =>
                            $test->minimum_specification,

                        'maximum_specification' =>
                            $test->maximum_specification,

                        'result' => $test->result,

                        'tested_at' =>
                            $test->tested_at
                                ?->toIso8601String(),
                    ]
                )
            ),

            'lot_release' => $this->whenLoaded(
                'lotRelease',
                fn (): ?array => $this->lotRelease === null
                    ? null
                    : [
                        'external_id' =>
                            $this->lotRelease->external_id,

                        'release_number' =>
                            $this->lotRelease
                                ->release_number,

                        'decision' =>
                            $this->lotRelease->decision,

                        'warehouse_status' =>
                            $this->lotRelease
                                ->warehouse_status,

                        'decision_at' =>
                            $this->lotRelease
                                ->decision_at
                                ?->toIso8601String(),
                    ]
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}