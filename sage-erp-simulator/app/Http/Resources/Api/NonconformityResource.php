<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NonconformityResource extends JsonResource
{
    /**
     * Canonical nonconformity payload consumed by SmartFactory DSS.
     *
     * The simulator stores nonconformity details directly on a failed
     * inspection. This resource exposes each failed inspection as one
     * stable nonconformity record without duplicating source tables.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $code =
            (string) (
                $this->nonconformity_code
                ?? 'NC-GENERAL'
            );

        $detectedAt =
            $this->inspection_completed_at
            ?? $this->inspection_started_at
            ?? $this->sampled_at;

        return [
            /*
             * Prefixing the inspection UUID creates a deterministic,
             * resource-specific external identity.
             */
            'external_id' =>
                'nonconformity-'
                .$this->external_id,

            'nonconformity_number' =>
                $code
                .'-'
                .$this->external_id,

            'inspection_external_id' =>
                $this->external_id,

            'batch_external_id' =>
                $this->productionBatch->external_id,

            'severity' =>
                $this->severityFor($code),

            /*
             * The source records a recommended corrective action but
             * does not record verified closure. Therefore the truthful
             * synchronization status is open and corrected_at is null.
             */
            'status' =>
                'open',

            'category' =>
                $this->categoryFor($code),

            'description' =>
                (string) (
                    $this->nonconformity_description
                    ?? 'Quality result outside the approved specification.'
                ),

            'detected_at' =>
                $detectedAt?->toIso8601String(),

            'corrected_at' =>
                null,

            'corrective_action' =>
                $this->corrective_action,

            'source_version' =>
                1,

            'source_updated_at' =>
                $this->source_updated_at
                    ?->toIso8601String(),
        ];
    }

    private function severityFor(
        string $code
    ): string {
        return match (
            strtoupper(trim($code))
        ) {
            'NC-MICRO' =>
                'critical',

            'NC-SENSORY' =>
                'minor',

            default =>
                'major',
        };
    }

    private function categoryFor(
        string $code
    ): string {
        return match (
            strtoupper(trim($code))
        ) {
            'NC-BRIX' =>
                'brix',

            'NC-PH' =>
                'ph',

            'NC-FILL' =>
                'fill_volume',

            'NC-PACK' =>
                'package_integrity',

            'NC-MICRO' =>
                'microbiology',

            'NC-SENSORY' =>
                'sensory',

            default =>
                'general',
        };
    }
}
