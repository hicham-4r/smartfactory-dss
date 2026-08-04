<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use stdClass;

class ApplySimulatedDataQualityScenario
{
    /**
     * Handle an incoming request.
     *
     * The simulation changes the JSON response only.
     * No database record is inserted, deleted, or modified.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $scenario = (string) $request->query(
            'dq_scenario',
            ''
        );

        /*
         * A normal API request remains completely unchanged when no
         * scenario was requested.
         */
        if ($scenario === '') {
            return $next($request);
        }

        if (!config('erp_data_quality.enabled', false)) {
            return response()->json([
                'message' =>
                    'Data-quality simulation is disabled.',

                'error_code' =>
                    'DATA_QUALITY_SIMULATION_DISABLED',
            ], 409);
        }

        $maximumRate = (int) config(
            'erp_data_quality.maximum_rate',
            100
        );

        $validator = Validator::make(
            $request->query(),
            [
                'dq_scenario' => [
                    'required',
                    'string',
                    'in:clean,missing,duplicates,mixed',
                ],

                'dq_missing_rate' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:' . $maximumRate,
                ],

                'dq_duplicate_rate' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:' . $maximumRate,
                ],

                'dq_seed' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:2147483647',
                ],

                'dq_fields' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' =>
                    'The data-quality simulation parameters are invalid.',

                'errors' => $validator->errors(),
            ], 422);
        }

        $routeKey = $request->path();

        /** @var array<int, string> $allowedFields */
        $allowedFields = config(
            'erp_data_quality.fields.' . $routeKey,
            []
        );

        $selectedFields = $this->selectedMissingFields(
            request: $request,
            allowedFields: $allowedFields
        );

        if (
            in_array($scenario, ['missing', 'mixed'], true)
            && $selectedFields === []
        ) {
            return response()->json([
                'message' =>
                    'No supported missing-data fields were found for this endpoint.',

                'errors' => [
                    'dq_fields' => [
                        'Choose one or more supported fields.',
                    ],
                ],

                'supported_fields' => $allowedFields,
            ], 422);
        }

        $invalidFields = array_values(
            array_diff(
                $selectedFields,
                $allowedFields
            )
        );

        if ($invalidFields !== []) {
            return response()->json([
                'message' =>
                    'One or more missing-data fields are unsupported.',

                'errors' => [
                    'dq_fields' => [
                        'Unsupported fields: '
                        . implode(', ', $invalidFields),
                    ],
                ],

                'supported_fields' => $allowedFields,
            ], 422);
        }

        $response = $next($request);

        /*
         * Only successful JSON list responses are modified.
         */
        if (
            !$response instanceof JsonResponse
            || !$response->isSuccessful()
        ) {
            return $response;
        }

        $payload = $response->getData(true);

        if (
            !isset($payload['data'])
            || !is_array($payload['data'])
        ) {
            return $response;
        }

        $seed = (int) $request->query(
            'dq_seed',
            config(
                'erp_data_quality.default_seed',
                20260725
            )
        );

        $missingRate = (int) $request->query(
            'dq_missing_rate',
            config(
                'erp_data_quality.default_missing_rate',
                15
            )
        );

        $duplicateRate = (int) $request->query(
            'dq_duplicate_rate',
            config(
                'erp_data_quality.default_duplicate_rate',
                10
            )
        );

        if ($scenario === 'clean') {
            $missingRate = 0;
            $duplicateRate = 0;
        }

        if ($scenario === 'missing') {
            $duplicateRate = 0;
        }

        if ($scenario === 'duplicates') {
            $missingRate = 0;
        }

        $items = array_values($payload['data']);

        $originalPageSize = count($items);

        $missingValuesApplied = 0;
        $duplicateRowsApplied = 0;

        if (
            in_array($scenario, ['missing', 'mixed'], true)
            && $missingRate > 0
        ) {
            [
                $items,
                $missingValuesApplied,
            ] = $this->applyMissingValues(
                items: $items,
                fields: $selectedFields,
                rate: $missingRate,
                seed: $seed,
                routeKey: $routeKey
            );
        }

        if (
            in_array(
                $scenario,
                ['duplicates', 'mixed'],
                true
            )
            && $duplicateRate > 0
        ) {
            [
                $items,
                $duplicateRowsApplied,
            ] = $this->applyDuplicateRows(
                items: $items,
                rate: $duplicateRate,
                seed: $seed,
                routeKey: $routeKey
            );
        }

        $payload['data'] = $items;

        if (
            !isset($payload['meta'])
            || !is_array($payload['meta'])
        ) {
            $payload['meta'] = [];
        }

        $payload['meta']['data_quality'] = [
            'scenario' => $scenario,
            'response_only' => true,
            'database_modified' => false,
            'deterministic' => true,
            'seed' => $seed,

            'missing_rate_percent' =>
                $missingRate,

            'duplicate_rate_percent' =>
                $duplicateRate,

            'missing_fields' =>
                in_array(
                    $scenario,
                    ['missing', 'mixed'],
                    true
                )
                    ? $selectedFields
                    : [],

            'missing_values_applied' =>
                $missingValuesApplied,

            'duplicate_rows_applied' =>
                $duplicateRowsApplied,

            'duplicate_strategy' =>
                'replace_within_current_page',

            'page_size_before' =>
                $originalPageSize,

            'page_size_after' =>
                count($items),
        ];

        $response->setData($payload);

        $response->headers->set(
            'X-Data-Quality-Scenario',
            $scenario
        );

        $response->headers->set(
            'X-Simulated-Missing-Values',
            (string) $missingValuesApplied
        );

        $response->headers->set(
            'X-Simulated-Duplicate-Rows',
            (string) $duplicateRowsApplied
        );

        return $response;
    }

    /**
     * @param array<int, string> $allowedFields
     *
     * @return array<int, string>
     */
    private function selectedMissingFields(
        Request $request,
        array $allowedFields
    ): array {
        $requestedFields = $request->query(
            'dq_fields'
        );

        if (
            !is_string($requestedFields)
            || trim($requestedFields) === ''
        ) {
            return $allowedFields;
        }

        return collect(explode(',', $requestedFields))
            ->map(
                fn (string $field): string =>
                    trim($field)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed>  $items
     * @param array<int, string> $fields
     *
     * @return array{0: array<int, mixed>, 1: int}
     */
    private function applyMissingValues(
        array $items,
        array $fields,
        int $rate,
        int $seed,
        string $routeKey
    ): array {
        $appliedCount = 0;

        foreach ($items as $itemIndex => $item) {
            foreach ($fields as $field) {
                $currentValue = $this->readExistingValue(
                    item: $item,
                    field: $field
                );

                if (
                    $currentValue['exists'] === false
                    || $currentValue['value'] === null
                ) {
                    continue;
                }

                $mutationKey = implode('|', [
                    $seed,
                    $routeKey,
                    'missing',
                    $itemIndex,
                    $field,
                ]);

                if (
                    !$this->percentageHit(
                        rate: $rate,
                        key: $mutationKey
                    )
                ) {
                    continue;
                }

                data_set(
                    $items[$itemIndex],
                    $field,
                    null
                );

                $appliedCount++;
            }
        }

        /*
         * A positive rate must generate at least one missing value on a
         * non-empty page. This makes small-page tests predictable.
         */
        if (
            $appliedCount === 0
            && $rate > 0
            && $items !== []
        ) {
            foreach ($items as $itemIndex => $item) {
                foreach ($fields as $field) {
                    $currentValue =
                        $this->readExistingValue(
                            item: $item,
                            field: $field
                        );

                    if (
                        $currentValue['exists'] === false
                        || $currentValue['value'] === null
                    ) {
                        continue;
                    }

                    data_set(
                        $items[$itemIndex],
                        $field,
                        null
                    );

                    $appliedCount++;

                    break 2;
                }
            }
        }

        return [
            $items,
            $appliedCount,
        ];
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return array{0: array<int, mixed>, 1: int}
     */
    private function applyDuplicateRows(
        array $items,
        int $rate,
        int $seed,
        string $routeKey
    ): array {
        $itemCount = count($items);

        if ($itemCount < 2 || $rate <= 0) {
            return [$items, 0];
        }

        $duplicateCount = (int) round(
            $itemCount * ($rate / 100)
        );

        $duplicateCount = max(
            1,
            $duplicateCount
        );

        /*
         * Index zero remains an original source record.
         */
        $duplicateCount = min(
            $itemCount - 1,
            $duplicateCount
        );

        $candidateIndexes = range(
            1,
            $itemCount - 1
        );

        usort(
            $candidateIndexes,
            function (
                int $first,
                int $second
            ) use ($seed, $routeKey): int {
                $firstScore = $this->score(
                    implode('|', [
                        $seed,
                        $routeKey,
                        'duplicate-target',
                        $first,
                    ])
                );

                $secondScore = $this->score(
                    implode('|', [
                        $seed,
                        $routeKey,
                        'duplicate-target',
                        $second,
                    ])
                );

                return $firstScore <=> $secondScore;
            }
        );

        $targetIndexes = array_slice(
            $candidateIndexes,
            0,
            $duplicateCount
        );

        sort($targetIndexes);

        foreach ($targetIndexes as $targetIndex) {
            /*
             * Select an earlier item, ensuring that a row never duplicates
             * itself.
             */
            $sourceScore = $this->score(
                implode('|', [
                    $seed,
                    $routeKey,
                    'duplicate-source',
                    $targetIndex,
                ])
            );

            $sourceIndex = $sourceScore
                % $targetIndex;

            $items[$targetIndex] =
                $items[$sourceIndex];
        }

        return [
            $items,
            count($targetIndexes),
        ];
    }

    /**
     * @return array{
     *     exists: bool,
     *     value: mixed
     * }
     */
    private function readExistingValue(
        mixed $item,
        string $field
    ): array {
        $missingValue = new stdClass();

        $value = data_get(
            $item,
            $field,
            $missingValue
        );

        return [
            'exists' => $value !== $missingValue,
            'value' => $value === $missingValue
                ? null
                : $value,
        ];
    }

    private function percentageHit(
        int $rate,
        string $key
    ): bool {
        if ($rate <= 0) {
            return false;
        }

        if ($rate >= 100) {
            return true;
        }

        return ($this->score($key) % 10000)
            < ($rate * 100);
    }

    private function score(string $value): int
    {
        return (int) hexdec(
            substr(
                hash('sha256', $value),
                0,
                7
            )
        );
    }
}