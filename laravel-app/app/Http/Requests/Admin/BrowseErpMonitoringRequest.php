<?php

namespace App\Http\Requests\Admin;

use App\Enums\ERP\ErpSyncRunStatus;
use App\Enums\ERP\ErpSyncTrigger;
use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class BrowseErpMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can(
                PermissionName
                    ::ViewSynchronizationLogs
                    ->value
            )
            && $user->can(
                PermissionName
                    ::ViewSystemHealth
                    ->value
            );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'source_system' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    ...array_map(
                        static fn (
                            ErpSyncRunStatus $status
                        ): string => $status->value,
                        ErpSyncRunStatus::cases()
                    ),
                ]),
            ],

            'trigger' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    ...array_map(
                        static fn (
                            ErpSyncTrigger $trigger
                        ): string => $trigger->value,
                        ErpSyncTrigger::cases()
                    ),
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sourceSystem = $this->input(
            'source_system'
        );

        $this->merge([
            'source_system' =>
                is_string($sourceSystem)
                    ? Str::lower(
                        trim($sourceSystem)
                    )
                    : config(
                        'erp-monitoring.source_system',
                        'simulated_sage'
                    ),

            'status' =>
                $this->input(
                    'status',
                    'all'
                ),

            'trigger' =>
                $this->input(
                    'trigger',
                    'all'
                ),

            'per_page' =>
                $this->input(
                    'per_page',
                    10
                ),
        ]);
    }

    /**
     * @return array{
     *     source_system: string,
     *     status: string,
     *     trigger: string,
     *     per_page: int
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'source_system' =>
                (string) $validated[
                    'source_system'
                ],

            'status' =>
                (string) (
                    $validated['status']
                    ?? 'all'
                ),

            'trigger' =>
                (string) (
                    $validated['trigger']
                    ?? 'all'
                ),

            'per_page' =>
                (int) (
                    $validated['per_page']
                    ?? 10
                ),
        ];
    }
}
