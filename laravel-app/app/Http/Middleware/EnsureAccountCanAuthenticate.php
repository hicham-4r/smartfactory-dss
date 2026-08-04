<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountCanAuthenticate
{
    /**
     * End an existing session when the account is inactive or locked.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {
        $user = $request->user();

        if (
            $user === null
            || $user->canAuthenticate()
        ) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Your session is no longer valid. Please sign in again.'
            );
    }
}