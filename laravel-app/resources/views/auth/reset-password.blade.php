@extends('layouts.auth')

@section('title', 'Reset password')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold text-secondary mb-2">
            Secure password reset
        </p>

        <h2 class="h3 fw-bold mb-2">
            Choose a new password
        </h2>

        <p class="text-muted-smartfactory mb-0">
            Use at least 12 characters with uppercase and lowercase
            letters, numbers and symbols.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('password.update') }}"
        novalidate
    >
        @csrf

        <input
            type="hidden"
            name="token"
            value="{{ request()->route('token') }}"
        >

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
                value="{{ old('email', $request->email) }}"
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
                New password
            </label>

            <div class="input-group">
                <input
                    x-bind:type="visible ? 'text' : 'password'"
                    id="password"
                    name="password"
                    class="form-control
                           @error('password') is-invalid @enderror"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="128"
                    required
                >

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    x-on:click="visible = ! visible"
                >
                    <span x-text="visible ? 'Hide' : 'Show'"></span>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label
                for="password_confirmation"
                class="form-label fw-semibold"
            >
                Confirm new password
            </label>

            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                class="form-control"
                autocomplete="new-password"
                minlength="12"
                maxlength="128"
                required
            >
        </div>

        <button
            type="submit"
            class="btn btn-smartfactory w-100"
        >
            Reset password
        </button>
    </form>
@endsection