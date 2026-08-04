<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Listeners\RecordSuccessfulLogin;
use App\Services\Auth\SecureLoginService;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(
        SecureLoginService $secureLoginService
    ): void {
        Fortify::createUsersUsing(
            CreateNewUser::class
        );

        Fortify::updateUserProfileInformationUsing(
            UpdateUserProfileInformation::class
        );

        Fortify::updateUserPasswordsUsing(
            UpdateUserPassword::class
        );

        Fortify::resetUserPasswordsUsing(
            ResetUserPassword::class
        );

        Fortify::redirectUserForTwoFactorAuthenticationUsing(
            RedirectIfTwoFactorAuthenticatable::class
        );

        Fortify::loginView(
            fn () => view('auth.login')
        );

        Fortify::twoFactorChallengeView(
            fn () => view(
                'auth.two-factor-challenge'
            )
        );

        Fortify::requestPasswordResetLinkView(
            fn () => view(
                'auth.forgot-password'
            )
        );

        Fortify::resetPasswordView(
            fn (Request $request) => view(
                'auth.reset-password',
                [
                    'request' => $request,
                ]
            )
        );

        Fortify::confirmPasswordView(
            fn () => view(
                'auth.confirm-password'
            )
        );

        Fortify::authenticateUsing(
            fn (Request $request) =>
                $secureLoginService->authenticate(
                    $request
                )
        );

        Event::listen(
            Login::class,
            RecordSuccessfulLogin::class
        );

        RateLimiter::for(
            'login',
            function (Request $request): array {
                $email = Str::transliterate(
                    Str::lower(
                        trim(
                            (string) $request->input(
                                Fortify::username()
                            )
                        )
                    )
                );

                $ipAddress =
                    $request->ip() ?: 'unknown';

                return [
                    Limit::perMinute(
                        max(
                            1,
                            (int) config(
                                'security.authentication.'
                                .'login_attempts_per_minute',
                                5
                            )
                        )
                    )->by(
                        'login-account:'
                        .$email
                        .'|'
                        .$ipAddress
                    ),

                    Limit::perMinute(
                        max(
                            1,
                            (int) config(
                                'security.authentication.'
                                .'login_attempts_per_ip_per_minute',
                                20
                            )
                        )
                    )->by(
                        'login-ip:'.$ipAddress
                    ),
                ];
            }
        );

        RateLimiter::for(
            'two-factor',
            function (Request $request): Limit {
                return Limit::perMinute(5)->by(
                    'two-factor:'
                    .(
                        $request
                            ->session()
                            ->get('login.id')
                        ?: $request->ip()
                    )
                );
            }
        );

        RateLimiter::for(
            'passkeys',
            function (Request $request): Limit {
                $credentialId = $request->input(
                    'credential.id'
                );

                return Limit::perMinute(10)->by(
                    'passkey:'
                    .(
                        $credentialId
                        ?: $request
                            ->session()
                            ->getId()
                    )
                    .'|'
                    .$request->ip()
                );
            }
        );
    }
}