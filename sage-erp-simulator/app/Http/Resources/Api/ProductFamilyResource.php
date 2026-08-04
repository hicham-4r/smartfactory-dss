<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductFamilyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'external_id' =>
                $this->external_id,

            'code' =>
                $this->code,

            'name' =>
                $this->name,

            'description' =>
                $this->description,

            'is_active' =>
                $this->is_active,

            'products_count' =>
                $this->whenCounted(
                    'products'
                ),

            'created_at' =>
                $this->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $this->updated_at
                    ?->toIso8601String(),
        ];
    }
}
