<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddSecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->headers->set(
            'X-Frame-Options',
            'DENY'
        );

        $response->headers->set(
            'Referrer-Policy',
            'same-origin'
        );

        $response->headers->set(
            'Permissions-Policy',
            (string) config(
                'security.headers.permissions_policy'
            )
        );

        $response->headers->set(
            'Cross-Origin-Opener-Policy',
            'same-origin'
        );

        $response->headers->set(
            'Cross-Origin-Resource-Policy',
            'same-origin'
        );

        $response->headers->set(
            'X-Permitted-Cross-Domain-Policies',
            'none'
        );

        $sensitiveHtml =
            $request->user() !== null
            || $request->routeIs(
                'two-factor.login',
                'security.two-factor.*'
            );

        if (
            $sensitiveHtml
            && $this->isHtmlResponse($response)
        ) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            );

            $response->headers->set(
                'Pragma',
                'no-cache'
            );

            $response->headers->set(
                'Expires',
                '0'
            );
        }

        if (
            app()->environment('production')
            && $request->isSecure()
        ) {
            $maxAge = max(
                0,
                (int) config(
                    'security.headers.hsts_max_age',
                    31536000
                )
            );

            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='
                .$maxAge
                .'; includeSubDomains'
            );
        }

        return $response;
    }

    private function isHtmlResponse(
        Response $response
    ): bool {
        $contentType = mb_strtolower(
            (string) $response
                ->headers
                ->get('Content-Type')
        );

        return str_contains(
            $contentType,
            'text/html'
        );
    }
}