<?php

namespace App\Http\Controllers\API\ERP;

use App\Enums\ERP\ErpResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\BrowseErpSimulatorResourceRequest;
use App\Services\ERP\Simulator\ErpSimulatorCursor;
use App\Services\ERP\Simulator\ErpSimulatorResourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class ErpSimulatorController extends Controller
{
    public function health(
        Request $request,
        ErpSimulatorResourceRegistry $registry
    ): JsonResponse {
        $availability =
            $registry->availability();

        $available = count(
            array_filter($availability)
        );

        $total = count($availability);

        $missingResources = array_keys(
            array_filter(
                $availability,
                static fn (bool $value): bool =>
                    ! $value
            )
        );

        $healthy =
            $available === $total;

        return response()
            ->json(
                [
                    'status' =>
                        $healthy
                            ? 'ok'
                            : 'degraded',

                    'service' =>
                        'SmartFactory Simulated Sage ERP',

                    'source_system' =>
                        'simulated_sage',

                    'version' => 'v1',

                    'resources' => [
                        'available' => $available,
                        'total' => $total,

                        'missing' =>
                            $missingResources,
                    ],

                    'checked_at' =>
                        CarbonImmutable::now()
                            ->utc()
                            ->toIso8601String(),

                    'request_id' =>
                        $this->requestId(
                            $request
                        ),
                ],
                $healthy ? 200 : 503
            );
    }

    public function index(
        BrowseErpSimulatorResourceRequest $request,
        ErpSimulatorResourceRegistry $registry,
        ErpSimulatorCursor $cursorService
    ): JsonResponse {
        $resource = ErpResource::from(
            (string) $request->route(
                'erpResource'
            )
        );

        try {
            $table =
                $registry->tableFor(
                    $resource
                );
        } catch (RuntimeException) {
            return response()->json(
                [
                    'error' =>
                        'resource_unavailable',

                    'message' =>
                        'The requested ERP resource is unavailable.',

                    'resource' =>
                        $resource->value,

                    'request_id' =>
                        $this->requestId(
                            $request
                        ),
                ],
                503
            );
        }

        $validated =
            $request->validated();

        $page = (int) (
            $validated['page']
            ?? 1
        );

        if (
            isset($validated['cursor'])
            && is_string(
                $validated['cursor']
            )
        ) {
            try {
                $cursorPage =
                    $cursorService->decode(
                        $validated['cursor'],
                        $resource
                    );
            } catch (
                InvalidArgumentException $exception
            ) {
                throw ValidationException
                    ::withMessages([
                        'cursor' =>
                            $exception->getMessage(),
                    ]);
            }

            if (
                isset($validated['page'])
                && $page !== $cursorPage
            ) {
                throw ValidationException
                    ::withMessages([
                        'cursor' =>
                            'The ERP cursor does not match the requested page.',
                    ]);
            }

            $page = $cursorPage;
        }

        $perPage = (int) (
            $validated['per_page']
            ?? config(
                'erp.connectors.simulated_sage.page_size',
                100
            )
        );

        $columns =
            $registry->columnsFor(
                $table
            );

        $definition =
            $registry->definition(
                $resource
            );

        $query = DB::table($table);

        $this->applyFilters(
            query: $query,
            validated: $validated,
            columns: $columns,
            dateColumns:
                $definition['date_columns'],
            registry: $registry
        );

        $this->applyStableOrdering(
            query: $query,
            columns: $columns,
            registry: $registry
        );

        $paginator = $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page
        );

        $records = array_map(
            fn (object $row): array =>
                $this->normalizeRow(
                    row: $row,
                    columns: $columns,
                    externalIdColumns:
                        $definition[
                            'external_id_columns'
                        ],
                    registry: $registry
                ),
            $paginator->items()
        );

        $nextPage =
            $paginator->hasMorePages()
                ? $paginator->currentPage() + 1
                : null;

        $nextCursor =
            $nextPage !== null
                ? $cursorService->encode(
                    $resource,
                    $nextPage
                )
                : null;

        $requestId =
            $this->requestId($request);

        return response()
            ->json([
                'data' => $records,

                'meta' => [
                    'resource' =>
                        $resource->value,

                    'current_page' =>
                        $paginator
                            ->currentPage(),

                    'per_page' =>
                        $paginator
                            ->perPage(),

                    'total' =>
                        $paginator->total(),

                    'last_page' =>
                        $paginator
                            ->lastPage(),

                    'next_page' =>
                        $nextPage,

                    'next_cursor' =>
                        $nextCursor,

                    'request_id' =>
                        $requestId,
                ],

                'links' => [
                    'next_cursor' =>
                        $nextCursor,
                ],
            ])
            ->withHeaders([
                'X-Request-ID' =>
                    $requestId,

                'X-ERP-Resource' =>
                    $resource->value,

                'X-ERP-Source' =>
                    'simulated_sage',
            ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @param list<string> $columns
     * @param list<string> $dateColumns
     */
    private function applyFilters(
        Builder $query,
        array $validated,
        array $columns,
        array $dateColumns,
        ErpSimulatorResourceRegistry $registry
    ): void {
        $updatedColumn =
            $registry->firstExistingColumn(
                [
                    'source_updated_at',
                    'updated_at',
                ],
                $columns
            );

        if (
            $updatedColumn !== null
            && isset(
                $validated['updated_since']
            )
        ) {
            $query->where(
                $updatedColumn,
                '>',
                CarbonImmutable::parse(
                    $validated[
                        'updated_since'
                    ]
                )
                    ->utc()
                    ->toDateTimeString()
            );
        }

        $versionColumn =
            $registry->firstExistingColumn(
                [
                    'source_version',
                    'version',
                    'lock_version',
                ],
                $columns
            );

        if (
            $versionColumn !== null
            && isset(
                $validated['source_version']
            )
        ) {
            $query->where(
                $versionColumn,
                '>',
                (int) $validated[
                    'source_version'
                ]
            );
        }

        $dateColumn =
            $registry->firstExistingColumn(
                $dateColumns,
                $columns
            );

        if (
            $dateColumn !== null
            && isset($validated['date_from'])
        ) {
            $query->whereDate(
                $dateColumn,
                '>=',
                $validated['date_from']
            );
        }

        if (
            $dateColumn !== null
            && isset($validated['date_to'])
        ) {
            $query->whereDate(
                $dateColumn,
                '<=',
                $validated['date_to']
            );
        }

        if (
            in_array(
                'status',
                $columns,
                true
            )
            && isset($validated['status'])
        ) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (
            in_array(
                'is_active',
                $columns,
                true
            )
            && array_key_exists(
                'is_active',
                $validated
            )
        ) {
            $query->where(
                'is_active',
                (bool) $validated[
                    'is_active'
                ]
            );
        }

        foreach (
            [
                'product_id',
                'production_line_id',
                'machine_id',
                'shift_id',
                'operator_id',
                'work_order_id',
                'batch_id',
            ] as $column
        ) {
            if (
                in_array(
                    $column,
                    $columns,
                    true
                )
                && isset($validated[$column])
            ) {
                $query->where(
                    $column,
                    (int) $validated[$column]
                );
            }
        }

        /*
         * Support the Phase 4 production table relationship names.
         */
        if (
            isset($validated['work_order_id'])
            && ! in_array(
                'work_order_id',
                $columns,
                true
            )
            && in_array(
                'production_order_id',
                $columns,
                true
            )
        ) {
            $query->where(
                'production_order_id',
                (int) $validated[
                    'work_order_id'
                ]
            );
        }

        if (
            isset($validated['batch_id'])
            && ! in_array(
                'batch_id',
                $columns,
                true
            )
            && in_array(
                'production_batch_id',
                $columns,
                true
            )
        ) {
            $query->where(
                'production_batch_id',
                (int) $validated[
                    'batch_id'
                ]
            );
        }
    }

    /**
     * @param list<string> $columns
     */
    private function applyStableOrdering(
        Builder $query,
        array $columns,
        ErpSimulatorResourceRegistry $registry
    ): void {
        $versionColumn =
            $registry->firstExistingColumn(
                [
                    'source_version',
                    'version',
                    'lock_version',
                ],
                $columns
            );

        if ($versionColumn !== null) {
            $query->orderBy(
                $versionColumn
            );
        }

        $updatedColumn =
            $registry->firstExistingColumn(
                [
                    'source_updated_at',
                    'updated_at',
                    'created_at',
                ],
                $columns
            );

        if ($updatedColumn !== null) {
            $query->orderBy(
                $updatedColumn
            );
        }

        if (
            in_array(
                'id',
                $columns,
                true
            )
        ) {
            $query->orderBy('id');
        }
    }

    /**
     * @param list<string> $columns
     * @param list<string> $externalIdColumns
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(
        object $row,
        array $columns,
        array $externalIdColumns,
        ErpSimulatorResourceRegistry $registry
    ): array {
        $record = (array) $row;

        foreach (
            array_keys($record)
            as $column
        ) {
            if (
                preg_match(
                    '/password|token|secret|authorization|remember|two_factor|recovery|api[_-]?key/i',
                    $column
                )
            ) {
                unset($record[$column]);
            }
        }

        $externalIdColumn =
            $registry->firstExistingColumn(
                $externalIdColumns,
                $columns
            );

        $externalId =
            $externalIdColumn !== null
                ? (
                    $record[
                        $externalIdColumn
                    ] ?? null
                )
                : null;

        if (
            $externalId === null
            || trim((string) $externalId) === ''
        ) {
            $externalId =
                $record['id']
                ?? null;
        }

        $versionColumn =
            $registry->firstExistingColumn(
                [
                    'source_version',
                    'version',
                    'lock_version',
                ],
                $columns
            );

        $sourceVersion =
            $versionColumn !== null
                ? (
                    $record[$versionColumn]
                    ?? 1
                )
                : 1;

        $updatedColumn =
            $registry->firstExistingColumn(
                [
                    'source_updated_at',
                    'updated_at',
                    'created_at',
                ],
                $columns
            );

        $sourceUpdatedAt =
            $updatedColumn !== null
                ? (
                    $record[$updatedColumn]
                    ?? null
                )
                : null;

        $record['external_id'] =
            (string) $externalId;

        $record['source_version'] =
            max(
                0,
                (int) $sourceVersion
            );

        $record['source_updated_at'] =
            $sourceUpdatedAt
            ?? CarbonImmutable::now()
                ->utc()
                ->toIso8601String();

        return $record;
    }

    private function requestId(
        Request $request
    ): string {
        return (string) (
            $request->attributes->get(
                'erp_request_id'
            )
            ?? $request->header(
                'X-Request-ID'
            )
            ?? ''
        );
    }
}