@extends('layouts.auth')

@section('title', 'Forgot password')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold text-secondary mb-2">
            Account recovery
        </p>

        <h2 class="h3 fw-bold mb-2">
            Reset your password
        </h2>

        <p class="text-muted-smartfactory mb-0">
            Enter your professional email address. If a matching account exists,
            password-reset instructions will be sent.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('password.email') }}"
        novalidate
    >
        @csrf

        <div class="mb-4">
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
                autocomplete="email"
                inputmode="email"
                maxlength="255"
                required
                autofocus
            >
        </div>

        <button
            type="submit"
            class="btn btn-smartfactory w-100"
        >
            Send reset instructions
        </button>
    </form>

    <div class="text-center mt-4">
        <a
            href="{{ route('login') }}"
            class="small fw-semibold text-decoration-none"
        >
            Return to sign in
        </a>
    </div>
@endsection