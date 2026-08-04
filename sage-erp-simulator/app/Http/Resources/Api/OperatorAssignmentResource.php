<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $externalId = sprintf(
            'OA-%03d',
            (int) $this->id
        );

        return [
            /*
             * The simulator table predates source external IDs for this
             * entity. Its immutable primary key is therefore converted into
             * a stable, non-secret ERP identifier.
             */
            'external_id' => $externalId,

            /*
             * Canonical flat relationship fields consumed by the DSS ERP
             * mapper.
             */
            'operator_external_id' =>
                $this->operator->external_id,

            'production_line_external_id' =>
                $this->productionLine->external_id,

            'shift_external_id' =>
                $this->shift->external_id,

            'valid_from' =>
                $this->assigned_from?->format('Y-m-d'),

            'valid_until' =>
                $this->assigned_until?->format('Y-m-d'),

            /*
             * Source-native aliases remain available for direct simulator
             * consumers.
             */
            'assigned_from' =>
                $this->assigned_from?->format('Y-m-d'),

            'assigned_until' =>
                $this->assigned_until?->format('Y-m-d'),

            'role_on_line' => $this->role_on_line,
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,

            'operator' => [
                'external_id' =>
                    $this->operator->external_id,

                'employee_code' =>
                    $this->operator->employee_code,

                'full_name' =>
                    $this->operator->full_name,
            ],

            'production_line' => [
                'external_id' =>
                    $this->productionLine->external_id,

                'code' =>
                    $this->productionLine->code,

                'name' =>
                    $this->productionLine->name,
            ],

            'shift' => [
                'external_id' =>
                    $this->shift->external_id,

                'code' =>
                    $this->shift->code,

                'name' =>
                    $this->shift->name,
            ],

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}
