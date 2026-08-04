<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Prevent users with temporary passwords from accessing
     * protected application modules.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->must_change_password) {
            return $next($request);
        }

        /*
         * These routes remain available while a mandatory password
         * change is pending.
         */
        if (
            $request->routeIs(
                'security.password.required',
                'security.password.required.update',
                'logout'
            )
        ) {
            return $next($request);
        }

        return redirect()
            ->route('security.password.required')
            ->with(
                'status',
                'You must replace your temporary password before continuing.'
            );
    }
}