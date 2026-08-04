<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdministratorHasTwoFactorAuthentication
{
    /**
     * Require confirmed 2FA for administrator application access.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        /*
         * Two-factor authentication is mandatory for administrators.
         * Other operational roles may continue without it for now.
         */
        if (
            ! $user->hasRole(
                RoleName::Administrator->value
            )
        ) {
            return $next($request);
        }

        if (
            $user->hasEnabledTwoFactorAuthentication()
        ) {
            return $next($request);
        }

        return redirect()
            ->route('security.two-factor.show')
            ->with(
                'status',
                'Administrator accounts must configure two-factor authentication before continuing.'
            );
    }
}