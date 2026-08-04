<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * Assign a server-generated correlation ID to every HTTP request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
         * Do not trust a client-supplied request ID. Always generate
         * a new server-controlled identifier.
         */
        $requestId = (string) Str::uuid();

        $request->attributes->set(
            'request_id',
            $requestId
        );

        $response = $next($request);

        $response->headers->set(
            'X-Request-ID',
            $requestId
        );

        return $response;
    }
}