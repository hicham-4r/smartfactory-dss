@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold text-secondary mb-2">
            Secure access
        </p>

        <h2 class="h3 fw-bold mb-2">
            Sign in to your account
        </h2>

        <p class="text-muted-smartfactory mb-0">
            Enter your authorized SmartFactory DSS credentials.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('login') }}"
        novalidate
    >
        @csrf

        <div class="mb-3">
            <label
                for="email"
                class="form-label fw-semibold"
            >
                Email address
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                autocomplete="username"
                inputmode="email"
                maxlength="255"
                required
                autofocus
            >
        </div>

        <div
            class="mb-3"
            x-data="{ visible: false }"
        >
            <label
                for="password"
                class="form-label fw-semibold"
            >
                Password
            </label>

            <div class="input-group">
                <input
                    x-bind:type="visible ? 'text' : 'password'"
                    id="password"
                    name="password"
                    class="form-control
                           @error('password') is-invalid @enderror"
                    autocomplete="current-password"
                    maxlength="128"
                    required
                >

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    x-on:click="visible = ! visible"
                    x-bind:aria-label="
                        visible ? 'Hide password' : 'Show password'
                    "
                >
                    <span x-text="visible ? 'Hide' : 'Show'"></span>
                </button>
            </div>
        </div>

        <div
            class="d-flex justify-content-between
                   align-items-center gap-3 mb-4"
        >
            <div class="form-check">
                <input
                    type="checkbox"
                    id="remember"
                    name="remember"
                    value="1"
                    class="form-check-input"
                    @checked(old('remember'))
                >

                <label
                    for="remember"
                    class="form-check-label"
                >
                    Remember me
                </label>
            </div>

            <a
                href="{{ route('password.request') }}"
                class="small fw-semibold text-decoration-none"
            >
                Forgot password?
            </a>
        </div>

        <button
            type="submit"
            class="btn btn-smartfactory w-100"
        >
            Sign in securely
        </button>
    </form>

    <div class="security-note rounded-3 p-3 mt-4 small">
        Accounts are created and managed by authorized administrators.
        Public registration is disabled.
    </div>
@endsection