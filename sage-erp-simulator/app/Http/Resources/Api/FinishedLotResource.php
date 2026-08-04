<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinishedLotResource extends JsonResource
{
    /**
     * Canonical finished-lot payload consumed by SmartFactory DSS.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $batch =
            $this->productionBatch;

        $product =
            $batch
                ->productionOrder
                ->product;

        $producedAt =
            $batch->actual_end_at
            ?? $batch->scheduled_end_at
            ?? $this->decision_at;

        return [
            'external_id' =>
                $this->external_id,

            'lot_number' =>
                $this->lot_number,

            'batch_external_id' =>
                $batch->external_id,

            'product_external_id' =>
                $product->external_id,

            'status' =>
                $this->decision,

            'produced_at' =>
                $producedAt?->toIso8601String(),

            'expiry_date' =>
                $this->expiry_date
                    ?->format('Y-m-d'),

            'produced_quantity' =>
                $batch->gross_quantity,

            'released_quantity' =>
                $this->approved_quantity,

            /*
             * Blocked quantity remains distinguishable through status
             * and the additional blocked_quantity field below. It must
             * not be falsely persisted as rejected quantity.
             */
            'rejected_quantity' =>
                $this->rejected_quantity,

            'quantity_unit' =>
                'bottles',

            'released_at' =>
                $this->released_at
                    ?->toIso8601String(),

            /*
             * released_by is a display name in the simulator, not a
             * stable operator external identifier.
             */
            'released_by_external_id' =>
                null,

            'release_notes' =>
                $this->decision_reason,

            'source_version' =>
                1,

            'source_updated_at' =>
                $this->source_updated_at
                    ?->toIso8601String(),

            /*
             * Additional source detail retained for diagnostics.
             */
            'release_number' =>
                $this->release_number,

            'blocked_quantity' =>
                $this->blocked_quantity,

            'warehouse_status' =>
                $this->warehouse_status,

            'quality_certificate_number' =>
                $this->quality_certificate_number,

            'released_by_name' =>
                $this->released_by,
        ];
    }
}
