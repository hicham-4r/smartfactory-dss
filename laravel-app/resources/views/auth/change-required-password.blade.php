@extends('layouts.auth')

@section('title', 'Change temporary password')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold text-secondary mb-2">
            First-login security
        </p>

        <h2 class="h3 fw-bold mb-2">
            Replace your temporary password
        </h2>

        <p class="text-muted-smartfactory mb-0">
            Your account was created with a temporary password.
            You must choose a private password before accessing
            SmartFactory DSS.
        </p>
    </div>

    <div
        class="security-note rounded-3 p-3 mb-4 small"
        role="status"
    >
        The new password must contain at least 12 characters,
        uppercase and lowercase letters, a number and a symbol.
    </div>

    <form
        method="POST"
        action="{{ route('security.password.required.update') }}"
        novalidate
    >
        @csrf
        @method('PUT')

        <div
            class="mb-3"
            x-data="{ visible: false }"
        >
            <label
                for="current_password"
                class="form-label fw-semibold"
            >
                Temporary password
            </label>

            <div class="input-group">
                <input
                    x-bind:type="visible ? 'text' : 'password'"
                    id="current_password"
                    name="current_password"
                    class="form-control
                           @error('current_password') is-invalid @enderror"
                    autocomplete="current-password"
                    maxlength="128"
                    required
                    autofocus
                >

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    x-on:click="visible = ! visible"
                    x-bind:aria-label="
                        visible
                            ? 'Hide temporary password'
                            : 'Show temporary password'
                    "
                >
                    <span x-text="visible ? 'Hide' : 'Show'"></span>
                </button>
            </div>
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
                    x-bind:aria-label="
                        visible
                            ? 'Hide new password'
                            : 'Show new password'
                    "
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
            Change password securely
        </button>
    </form>

    <div class="border-top mt-4 pt-4">
        <form
            method="POST"
            action="{{ route('logout') }}"
        >
            @csrf

            <button
                type="submit"
                class="btn btn-link text-danger p-0
                       text-decoration-none small"
            >
                Sign out instead
            </button>
        </form>
    </div>
@endsection