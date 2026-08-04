@extends('layouts.auth')

@section('title', 'Confirm password')

@section('content')
    <div class="mb-4">
        <p class="text-uppercase small fw-semibold text-secondary mb-2">
            Sensitive action
        </p>

        <h2 class="h3 fw-bold mb-2">
            Confirm your password
        </h2>

        <p class="text-muted-smartfactory mb-0">
            Re-enter your password before continuing with this
            protected operation.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('password.confirm') }}"
        novalidate
    >
        @csrf

        <div class="mb-4">
            <label
                for="password"
                class="form-label fw-semibold"
            >
                Current password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                class="form-control
                       @error('password') is-invalid @enderror"
                autocomplete="current-password"
                maxlength="128"
                required
                autofocus
            >
        </div>

        <button
            type="submit"
            class="btn btn-smartfactory w-100"
        >
            Confirm securely
        </button>
    </form>
@endsection