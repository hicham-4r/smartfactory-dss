<?php

namespace App\Http\Controllers\Security;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Fortify\Fortify;

final class TwoFactorSecurityController extends Controller
{
    /**
     * Display administrator 2FA configuration.
     */
    public function show(
        Request $request
    ): Response {
        $user = $this->administrator(
            $request
        );

        $showRecoveryCodes =
            $user->hasEnabledTwoFactorAuthentication()
            && (
                session(
                    'show_two_factor_recovery_codes',
                    false
                )
                || in_array(
                    session('status'),
                    [
                        Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED,
                        Fortify::RECOVERY_CODES_GENERATED,
                    ],
                    true
                )
            );

        $recoveryCodes = $showRecoveryCodes
            ? $user->recoveryCodes()
            : [];

        return response()
            ->view(
                'security.two-factor',
                [
                    'user' => $user,
                    'showRecoveryCodes' =>
                        $showRecoveryCodes,
                    'recoveryCodes' =>
                        $recoveryCodes,
                ]
            )
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Reveal existing recovery codes for one response only.
     */
    public function revealRecoveryCodes(
        Request $request
    ): RedirectResponse {
        $user = $this->administrator(
            $request
        );

        abort_unless(
            $user->hasEnabledTwoFactorAuthentication(),
            404
        );

        return redirect()
            ->route('security.two-factor.show')
            ->with(
                'show_two_factor_recovery_codes',
                true
            );
    }

    /**
     * Return and validate the authenticated administrator.
     */
    private function administrator(
        Request $request
    ): User {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->hasRole(
                RoleName::Administrator->value
            ),
            403
        );

        return $user;
    }
}