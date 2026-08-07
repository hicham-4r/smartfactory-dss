<?php

namespace App\Http\Middleware;

use App\Support\Observability\NativeMetricsStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordNativeMetrics
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        if ($request->is('api/metrics')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $statusCode = 500;

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();

            return $response;
        } finally {
            try {
                $route = $request->route();
                $routeLabel = $route?->getName()
                    ?: $route?->uri()
                    ?: 'unmatched';

                NativeMetricsStore::fromConfig()->observe(
                    method: $request->getMethod(),
                    route: (string) $routeLabel,
                    statusCode: $statusCode,
                    durationSeconds: max(
                        0.0,
                        (hrtime(true) - $startedAt) / 1_000_000_000
                    ),
                );
            } catch (Throwable) {
                // Native metrics are fail-open by design.
            }
        }
    }
}
