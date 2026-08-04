<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
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
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'crosses_midnight' => $this->crosses_midnight,
            'status' => $this->status,
            'is_active' => $this->is_active,

            'operator_assignments_count' =>
                $this->whenCounted(
                    'operatorAssignments'
                ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}