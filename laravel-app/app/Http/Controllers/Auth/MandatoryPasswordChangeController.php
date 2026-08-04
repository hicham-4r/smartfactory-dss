<?php

namespace App\Http\Controllers\Auth;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateMandatoryPasswordRequest;
use App\Services\Auth\MandatoryPasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MandatoryPasswordChangeController extends Controller
{
    /**
     * Display the mandatory password-change form.
     */
    public function edit(
        Request $request
    ): Response|RedirectResponse {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->route(
                $this->destinationRoute($user)
            );
        }

        /*
         * Prevent browsers and proxies from caching a sensitive
         * password-entry page.
         */
        return response()
            ->view('auth.change-required-password')
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private, max-age=0'
            )
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Validate and replace the temporary password.
     */
    public function update(
        UpdateMandatoryPasswordRequest $request,
        MandatoryPasswordChangeService $passwordChangeService
    ): RedirectResponse {
        $validated = $request->validated();

        $passwordChangeService->changePassword(
            $request->user(),
            $validated['password']
        );

        /*
         * Refresh the authenticated session after the security-sensitive
         * credential change.
         */
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route(
                $this->destinationRoute(
                    $request->user()
                )
            )
            ->with(
                'status',
                'Your password was changed successfully.'
            );
    }

    /**
     * Choose the appropriate destination after password replacement.
     */
    private function destinationRoute(
        object $user
    ): string {
        if (
            $user->can(
                PermissionName::ViewAdministratorDashboard->value
            )
        ) {
            return 'admin.dashboard';
        }

        return 'dashboard';
    }
}