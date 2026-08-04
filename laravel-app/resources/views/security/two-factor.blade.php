@extends('layouts.app')

@section('title', 'Two-factor security')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="mb-4">
                <p
                    class="text-uppercase small fw-semibold
                           text-secondary mb-2"
                >
                    Administrator security
                </p>

                <h1 class="h3 fw-bold mb-2">
                    Two-factor authentication
                </h1>

                <p class="text-muted-smartfactory mb-0">
                    Protect your administrator account with an
                    authenticator application and recovery codes.
                </p>
            </div>

            @if (
                ! $user->two_factor_secret
            )
                <div class="app-card bg-white p-4">
                    <h2 class="h5 fw-bold mb-3">
                        Two-factor authentication is required
                    </h2>

                    <p class="text-muted-smartfactory">
                        Enabling 2FA creates an encrypted secret and
                        eight one-time recovery codes.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('two-factor.enable') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="btn btn-smartfactory"
                        >
                            Begin secure 2FA setup
                        </button>
                    </form>
                </div>
            @elseif (
                ! $user->hasEnabledTwoFactorAuthentication()
            )
                <div class="app-card bg-white p-4 mb-4">
                    <h2 class="h5 fw-bold mb-3">
                        Scan the QR code
                    </h2>

                    <p class="text-muted-smartfactory">
                        Scan this QR code using a TOTP-compatible
                        authenticator application.
                    </p>

                    <div
                        class="d-inline-block bg-white border
                               rounded-3 p-3 mb-4"
                    >
                        {!! $user->twoFactorQrCodeSvg() !!}
                    </div>

                    @if (
                        $errors
                            ->confirmTwoFactorAuthentication
                            ->any()
                    )
                        <div
                            class="alert alert-danger"
                            role="alert"
                        >
                            @foreach (
                                $errors
                                    ->confirmTwoFactorAuthentication
                                    ->all()
                                as $error
                            )
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('two-factor.confirm') }}"
                        class="mb-4"
                    >
                        @csrf

                        <div class="mb-3">
                            <label
                                for="code"
                                class="form-label fw-semibold"
                            >
                                Six-digit authentication code
                            </label>

                            <input
                                type="text"
                                id="code"
                                name="code"
                                class="form-control"
                                autocomplete="one-time-code"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                required
                                autofocus
                            >
                        </div>

                        <button
                            type="submit"
                            class="btn btn-smartfactory"
                        >
                            Confirm and activate 2FA
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('two-factor.disable') }}"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-outline-danger"
                        >
                            Cancel setup
                        </button>
                    </form>
                </div>
            @else
                <div class="app-card bg-white p-4 mb-4">
                    <div
                        class="d-flex flex-wrap
                               justify-content-between
                               align-items-start gap-3"
                    >
                        <div>
                            <h2 class="h5 fw-bold mb-2">
                                Two-factor authentication is active
                            </h2>

                            <p class="text-muted-smartfactory mb-0">
                                Future logins require your password and
                                an authenticator or recovery code.
                            </p>
                        </div>

                        <span class="badge text-bg-success">
                            Enabled
                        </span>
                    </div>
                </div>

                @if (
                    $showRecoveryCodes
                    && $recoveryCodes !== []
                )
                    <div
                        class="app-card bg-white p-4 mb-4"
                    >
                        <h2 class="h5 fw-bold mb-3">
                            Recovery codes
                        </h2>

                        <div
                            class="alert alert-warning"
                            role="alert"
                        >
                            Store these codes securely. They will not
                            be displayed continuously.
                        </div>

                        <div class="row g-2">
                            @foreach ($recoveryCodes as $code)
                                <div class="col-md-6">
                                    <div
                                        class="border rounded-3
                                               p-3 font-monospace"
                                    >
                                        {{ $code }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="app-card bg-white p-4 mb-4">
                    <h2 class="h5 fw-bold mb-3">
                        Recovery-code management
                    </h2>

                    <div class="d-flex flex-wrap gap-2">
                        <form
                            method="POST"
                            action="{{
                                route(
                                    'security.two-factor.recovery-codes.reveal'
                                )
                            }}"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="btn btn-outline-primary"
                            >
                                Reveal recovery codes
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="{{
                                route(
                                    'two-factor.regenerate-recovery-codes'
                                )
                            }}"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="btn btn-outline-warning"
                            >
                                Generate new recovery codes
                            </button>
                        </form>
                    </div>
                </div>

                <div class="app-card bg-white p-4">
                    <h2 class="h5 fw-bold text-danger mb-3">
                        Replace authenticator device
                    </h2>

                    <p class="text-muted-smartfactory">
                        Disabling 2FA immediately removes the current
                        secret and recovery codes. Administrator access
                        will remain blocked until 2FA is configured again.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('two-factor.disable') }}"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-outline-danger"
                        >
                            Disable and configure again
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection