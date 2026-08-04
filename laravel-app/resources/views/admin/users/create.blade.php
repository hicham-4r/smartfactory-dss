@extends('layouts.app')

@section('title', 'Create user')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="mb-4">
                <a
                    href="{{ route('admin.users.index') }}"
                    class="text-decoration-none small fw-semibold"
                >
                    ← Return to users
                </a>

                <h1 class="h3 fw-bold mt-3 mb-2">
                    Create an internal user
                </h1>

                <p class="text-muted-smartfactory mb-0">
                    The system will generate a secure temporary password.
                    The user must replace it during the first login.
                </p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="app-card bg-white p-4">
                <form
                    method="POST"
                    action="{{ route('admin.users.store') }}"
                    novalidate
                >
                    @csrf

                    <div class="mb-3">
                        <label
                            for="name"
                            class="form-label fw-semibold"
                        >
                            Full name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            class="form-control
                                   @error('name') is-invalid @enderror"
                            minlength="2"
                            maxlength="120"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="email"
                            class="form-label fw-semibold"
                        >
                            Professional email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control
                                   @error('email') is-invalid @enderror"
                            autocomplete="off"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label
                            for="role"
                            class="form-label fw-semibold"
                        >
                            Role
                        </label>

                        <select
                            id="role"
                            name="role"
                            class="form-select
                                   @error('role') is-invalid @enderror"
                            required
                        >
                            <option value="">
                                Select one role
                            </option>

                            @foreach ($roles as $role)
                                <option
                                    value="{{ $role['value'] }}"
                                    @selected(
                                        old('role')
                                        === $role['value']
                                    )
                                >
                                    {{ $role['label'] }}
                                </option>
                            @endforeach
                        </select>

                        <div class="form-text">
                            Each user receives exactly one operational role.
                        </div>
                    </div>

                    <div
                        class="security-note rounded-3 p-3
                               mb-4 small"
                    >
                        Creating an account is a sensitive administrative
                        action and may require password confirmation.
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="btn btn-smartfactory"
                        >
                            Create secure account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection