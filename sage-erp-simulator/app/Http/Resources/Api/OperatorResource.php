<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,

            'hire_date' =>
                $this->hire_date?->format('Y-m-d'),

            'status' => $this->status,
            'is_active' => $this->is_active,

            'assignments' => $this->whenLoaded(
                'assignments',
                fn () => $this->assignments->map(
                    fn ($assignment): array => [
                        'role_on_line' =>
                            $assignment->role_on_line,

                        'assigned_from' =>
                            $assignment->assigned_from
                                ?->format('Y-m-d'),

                        'assigned_until' =>
                            $assignment->assigned_until
                                ?->format('Y-m-d'),

                        'is_primary' =>
                            $assignment->is_primary,

                        'is_active' =>
                            $assignment->is_active,

                        'production_line' => [
                            'external_id' =>
                                $assignment
                                    ->productionLine
                                    ->external_id,

                            'code' =>
                                $assignment
                                    ->productionLine
                                    ->code,

                            'name' =>
                                $assignment
                                    ->productionLine
                                    ->name,
                        ],

                        'shift' => [
                            'external_id' =>
                                $assignment
                                    ->shift
                                    ->external_id,

                            'code' =>
                                $assignment
                                    ->shift
                                    ->code,

                            'name' =>
                                $assignment
                                    ->shift
                                    ->name,
                        ],
                    ]
                )
            ),

            'created_at' =>
                $this->created_at?->toIso8601String(),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}