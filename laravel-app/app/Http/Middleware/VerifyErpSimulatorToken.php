<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class VerifyErpSimulatorToken
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $requestId = $this->requestId(
            $request
        );

        $request->attributes->set(
            'erp_request_id',
            $requestId
        );

        if (
            (bool) config(
                'erp.simulator.enforce_https',
                true
            )
            && ! $request->isSecure()
        ) {
            return $this->errorResponse(
                requestId: $requestId,
                status: 426,
                error: 'https_required',
                message:
                    'The ERP simulator API requires HTTPS.'
            );
        }

        $expectedToken = trim(
            (string) config(
                'erp.simulator.token',
                ''
            )
        );

        if (
            $expectedToken === ''
            || strlen($expectedToken) < 32
        ) {
            return $this->errorResponse(
                requestId: $requestId,
                status: 503,
                error:
                    'erp_simulator_not_configured',
                message:
                    'The ERP simulator API is not configured.'
            );
        }

        $providedToken =
            $request->bearerToken();

        if (
            ! is_string($providedToken)
            || $providedToken === ''
            || ! $this->tokensMatch(
                $expectedToken,
                $providedToken
            )
        ) {
            $this->recordRejectedRequest(
                $request,
                $requestId
            );

            return $this->errorResponse(
                requestId: $requestId,
                status: 401,
                error: 'unauthenticated',
                message:
                    'A valid ERP bearer token is required.'
            )->withHeaders([
                'WWW-Authenticate' =>
                    'Bearer realm="SmartFactory ERP Simulator"',
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(
            'X-Request-ID',
            $requestId
        );

        $response->headers->set(
            'Cache-Control',
            'no-store, private, max-age=0'
        );

        $response->headers->set(
            'Pragma',
            'no-cache'
        );

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        return $response;
    }

    private function tokensMatch(
        string $expected,
        string $provided
    ): bool {
        /*
         * Hashing both values before hash_equals prevents token
         * length from affecting the comparison operation.
         */
        return hash_equals(
            hash('sha256', $expected),
            hash('sha256', $provided)
        );
    }

    private function requestId(
        Request $request
    ): string {
        $provided = trim(
            (string) $request->header(
                'X-Request-ID',
                ''
            )
        );

        if (
            $provided !== ''
            && preg_match(
                '/^[A-Za-z0-9._:-]{1,100}$/',
                $provided
            )
        ) {
            return $provided;
        }

        return (string) Str::uuid();
    }

    private function recordRejectedRequest(
        Request $request,
        string $requestId
    ): void {
        /*
         * Never log the Authorization header or provided token.
         */
        Log::channel(
            (string) config(
                'erp.logging.channel',
                'stack'
            )
        )->notice(
            'ERP simulator authentication rejected.',
            [
                'request_id' => $requestId,

                'path' =>
                    '/'.$request->path(),

                'ip_fingerprint' =>
                    substr(
                        hash(
                            'sha256',
                            (string) $request->ip()
                        ),
                        0,
                        16
                    ),
            ]
        );
    }

    private function errorResponse(
        string $requestId,
        int $status,
        string $error,
        string $message
    ): JsonResponse {
        return response()
            ->json(
                [
                    'error' => $error,
                    'message' => $message,
                    'request_id' => $requestId,
                ],
                $status
            )
            ->withHeaders([
                'X-Request-ID' =>
                    $requestId,

                'Cache-Control' =>
                    'no-store, private, max-age=0',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]);
    }
}