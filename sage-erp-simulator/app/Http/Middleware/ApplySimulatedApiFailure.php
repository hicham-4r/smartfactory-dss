<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ApplySimulatedApiFailure
{
    /**
     * Apply a controlled artificial failure to the API response.
     *
     * The simulator database is never modified.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $scenario = (string) $request->query(
            'failure_scenario',
            ''
        );

        /*
         * Normal requests remain completely unchanged.
         */
        if ($scenario === '') {
            return $next($request);
        }

        if (
            !config(
                'erp_failure_simulation.enabled',
                false
            )
        ) {
            return response()->json([
                'message' =>
                    'Artificial API-failure simulation is disabled.',

                'error_code' =>
                    'FAILURE_SIMULATION_DISABLED',

                'simulated' => true,
            ], 409);
        }

        $configuredKey = (string) config(
            'erp_failure_simulation.key',
            ''
        );

        if ($configuredKey === '') {
            return response()->json([
                'message' =>
                    'The artificial failure simulation key is not configured.',

                'error_code' =>
                    'FAILURE_SIMULATION_KEY_NOT_CONFIGURED',

                'simulated' => true,
            ], 409);
        }

        $providedKey = (string) $request->header(
            'X-ERP-Failure-Key',
            ''
        );

        if (
            $providedKey === ''
            || !hash_equals(
                $configuredKey,
                $providedKey
            )
        ) {
            return response()->json([
                'message' =>
                    'A valid failure-simulation key is required.',

                'error_code' =>
                    'INVALID_FAILURE_SIMULATION_KEY',

                'simulated' => true,
            ], 403);
        }

        $maximumDelay = (int) config(
            'erp_failure_simulation.maximum_delay_ms',
            5000
        );

        $validator = Validator::make(
            $request->query(),
            [
                'failure_scenario' => [
                    'required',
                    'string',
                    'in:none,service_unavailable,gateway_timeout,rate_limited,internal_error,malformed_response,slow_response',
                ],

                'failure_probability' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:100',
                ],

                'failure_seed' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:2147483647',
                ],

                'failure_retry_after' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:3600',
                ],

                'failure_delay_ms' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:' . $maximumDelay,
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'message' =>
                    'The artificial failure parameters are invalid.',

                'errors' => $validator->errors(),

                'simulated' => true,
            ], 422);
        }

        $probability = (int) $request->query(
            'failure_probability',
            config(
                'erp_failure_simulation.default_probability',
                100
            )
        );

        $seed = (int) $request->query(
            'failure_seed',
            config(
                'erp_failure_simulation.default_seed',
                20260725
            )
        );

        $retryAfter = (int) $request->query(
            'failure_retry_after',
            config(
                'erp_failure_simulation.default_retry_after_seconds',
                30
            )
        );

        $delayMilliseconds = (int) $request->query(
            'failure_delay_ms',
            config(
                'erp_failure_simulation.default_delay_ms',
                1500
            )
        );

        $failureId = $this->failureId(
            seed: $seed,
            scenario: $scenario,
            request: $request
        );

        $triggered = $scenario !== 'none'
            && $this->percentageHit(
                rate: $probability,
                key: implode('|', [
                    $seed,
                    $request->method(),
                    $request->path(),
                    $scenario,
                    $request->getQueryString(),
                ])
            );

        /*
         * The configured probability did not trigger a failure.
         */
        if (!$triggered) {
            $response = $next($request);

            $response->headers->set(
                'X-Simulated-Failure-Scenario',
                $scenario
            );

            $response->headers->set(
                'X-Simulated-Failure-Triggered',
                'false'
            );

            $response->headers->set(
                'X-Simulated-Failure-ID',
                $failureId
            );

            return $response;
        }

        return match ($scenario) {
            'service_unavailable' =>
                $this->errorResponse(
                    status: 503,
                    scenario: $scenario,
                    errorCode:
                        'SIMULATED_SERVICE_UNAVAILABLE',
                    message:
                        'The simulated ERP service is temporarily unavailable.',
                    retryable: true,
                    failureId: $failureId,
                    retryAfter: $retryAfter
                ),

            'gateway_timeout' =>
                $this->errorResponse(
                    status: 504,
                    scenario: $scenario,
                    errorCode:
                        'SIMULATED_GATEWAY_TIMEOUT',
                    message:
                        'The simulated ERP request exceeded its response deadline.',
                    retryable: true,
                    failureId: $failureId,
                    retryAfter: $retryAfter
                ),

            'rate_limited' =>
                $this->errorResponse(
                    status: 429,
                    scenario: $scenario,
                    errorCode:
                        'SIMULATED_RATE_LIMIT_EXCEEDED',
                    message:
                        'The simulated ERP request rate limit was exceeded.',
                    retryable: true,
                    failureId: $failureId,
                    retryAfter: $retryAfter
                ),

            'internal_error' =>
                $this->errorResponse(
                    status: 500,
                    scenario: $scenario,
                    errorCode:
                        'SIMULATED_INTERNAL_ERROR',
                    message:
                        'The simulated ERP encountered an internal processing error.',
                    retryable: false,
                    failureId: $failureId
                ),

            'malformed_response' =>
                $this->malformedResponse(
                    scenario: $scenario,
                    failureId: $failureId
                ),

            'slow_response' =>
                $this->slowResponse(
                    request: $request,
                    next: $next,
                    scenario: $scenario,
                    failureId: $failureId,
                    delayMilliseconds:
                        $delayMilliseconds
                ),

            default => $next($request),
        };
    }

    private function errorResponse(
        int $status,
        string $scenario,
        string $errorCode,
        string $message,
        bool $retryable,
        string $failureId,
        ?int $retryAfter = null
    ): Response {
        $headers = [
            'X-Simulated-Failure-Scenario' =>
                $scenario,

            'X-Simulated-Failure-Triggered' =>
                'true',

            'X-Simulated-Failure-ID' =>
                $failureId,

            'Cache-Control' =>
                'no-store',
        ];

        if ($retryAfter !== null) {
            $headers['Retry-After'] =
                (string) $retryAfter;
        }

        return response()->json([
            'message' => $message,
            'error_code' => $errorCode,

            'service' =>
                'sage-erp-simulator',

            'data_source' =>
                'simulated',

            'simulated' => true,

            'failure' => [
                'scenario' => $scenario,
                'failure_id' => $failureId,
                'retryable' => $retryable,
                'database_modified' => false,
                'timestamp' =>
                    now()->toIso8601String(),
            ],
        ], $status, $headers);
    }

    private function malformedResponse(
        string $scenario,
        string $failureId
    ): Response {
        return response()->json([
            /*
             * Deliberately violates the normal API contract:
             *
             * - No "data" array
             * - No pagination "links"
             * - records is a string instead of an array
             */

            'unexpected_payload' =>
                'simulated-invalid-contract',

            'records' =>
                'not-an-array',

            'successful' =>
                'unknown',

            'simulated_failure' => [
                'scenario' => $scenario,
                'failure_id' => $failureId,
                'database_modified' => false,
            ],
        ], 200, [
            'X-Simulated-Failure-Scenario' =>
                $scenario,

            'X-Simulated-Failure-Triggered' =>
                'true',

            'X-Simulated-Failure-ID' =>
                $failureId,

            'Cache-Control' =>
                'no-store',
        ]);
    }

    private function slowResponse(
        Request $request,
        Closure $next,
        string $scenario,
        string $failureId,
        int $delayMilliseconds
    ): Response {
        if ($delayMilliseconds > 0) {
            usleep(
                $delayMilliseconds * 1000
            );
        }

        $response = $next($request);

        $response->headers->set(
            'X-Simulated-Failure-Scenario',
            $scenario
        );

        $response->headers->set(
            'X-Simulated-Failure-Triggered',
            'true'
        );

        $response->headers->set(
            'X-Simulated-Failure-ID',
            $failureId
        );

        $response->headers->set(
            'X-Simulated-Delay-Milliseconds',
            (string) $delayMilliseconds
        );

        return $response;
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

    private function failureId(
        int $seed,
        string $scenario,
        Request $request
    ): string {
        return substr(
            hash(
                'sha256',
                implode('|', [
                    $seed,
                    $scenario,
                    $request->method(),
                    $request->path(),
                    $request->getQueryString(),
                ])
            ),
            0,
            16
        );
    }
}