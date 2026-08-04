<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    /**
     * Canonical inspection payload consumed by SmartFactory DSS.
     *
     * The simulator stores only an inspector display name, not a stable
     * operator external identifier. Therefore inspector_external_id is
     * deliberately null instead of misusing a person's name as a key.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inspectedAt =
            $this->inspection_completed_at
            ?? $this->inspection_started_at
            ?? $this->sampled_at;

        return [
            'external_id' =>
                $this->external_id,

            'inspection_number' =>
                $this->inspection_number,

            'batch_external_id' =>
                $this->productionBatch->external_id,

            'finished_lot_external_id' =>
                $this->lotRelease?->external_id,

            'inspector_external_id' =>
                null,

            'inspection_type' =>
                $this->inspection_type,

            'result' =>
                $this->result,

            'inspected_at' =>
                $inspectedAt?->toIso8601String(),

            /*
             * The simulator records six test measurements per
             * inspection but does not store an actual physical sample
             * count. Do not misrepresent test count as sample size.
             */
            'sample_size' =>
                null,

            'passed_quantity' =>
                null,

            'failed_quantity' =>
                null,

            'notes' =>
                $this->nonconformity_description,

            'source_version' =>
                1,

            'source_updated_at' =>
                $this->source_updated_at
                    ?->toIso8601String(),

            /*
             * Additional non-key diagnostic information is safe to
             * expose and ignored by the DSS mapper.
             */
            'inspector_name' =>
                $this->inspector_name,

            'overall_score_percent' =>
                $this->overall_score_percent,
        ];
    }
}
