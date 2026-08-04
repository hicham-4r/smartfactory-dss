<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyErpApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $configuredToken = config('erp.api_token');
        $providedToken = $request->header('X-ERP-Token');

        if (
            !is_string($configuredToken)
            || $configuredToken === ''
        ) {
            return new JsonResponse([
                'message' => 'The simulated ERP API token is not configured.',
                'error_code' => 'ERP_TOKEN_NOT_CONFIGURED',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (
            !is_string($providedToken)
            || $providedToken === ''
            || !hash_equals($configuredToken, $providedToken)
        ) {
            return new JsonResponse([
                'message' => 'Unauthorized simulated ERP API request.',
                'error_code' => 'INVALID_ERP_TOKEN',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $response = $next($request);

        $response->headers->set(
            'X-Data-Source',
            'simulated'
        );

        $response->headers->set(
            'Cache-Control',
            'private, no-store'
        );

        return $response;
    }
}