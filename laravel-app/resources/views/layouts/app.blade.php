<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <meta
        name="robots"
        content="noindex, nofollow, noarchive"
    >

    <meta
        name="referrer"
        content="same-origin"
    >

    <title>
        @yield('title', 'Dashboard')
        | {{ config('app.name', 'SmartFactory DSS') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
    <div class="container-fluid px-3 px-lg-4">
        <a
            class="navbar-brand fw-bold"
            href="{{ route('dashboard') }}"
        >
            SmartFactory DSS
        </a>

        @auth
            <div class="d-flex align-items-center gap-2 text-white">
                @can(\App\Enums\PermissionName::ViewProductionKpis->value)
                    <a
                        class="btn btn-sm btn-outline-light"
                        href="{{ route('reports.index') }}"
                    >
                        Reports
                    </a>
                @endcan

                @include(
                    'notifications.partials.nav-link'
                )

                <span class="small d-none d-md-inline">
                    {{ auth()->user()->name }}
                </span>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                    class="m-0"
                >
                    @csrf

                    <button
                        type="submit"
                        class="btn btn-sm btn-outline-light"
                    >
                        Sign out
                    </button>
                </form>
            </div>
        @endauth
    </div>
</nav>

<main class="app-shell">
    <div class="container-fluid p-3 p-lg-4">
        @php
            $statusMessage = match (session('status')) {
                \Laravel\Fortify\Fortify
                    ::TWO_FACTOR_AUTHENTICATION_ENABLED =>
                    'Two-factor setup started. Scan the QR code and confirm the generated code.',

                \Laravel\Fortify\Fortify
                    ::TWO_FACTOR_AUTHENTICATION_CONFIRMED =>
                    'Two-factor authentication was confirmed successfully.',

                \Laravel\Fortify\Fortify
                    ::TWO_FACTOR_AUTHENTICATION_DISABLED =>
                    'Two-factor authentication was disabled.',

                \Laravel\Fortify\Fortify
                    ::RECOVERY_CODES_GENERATED =>
                    'New recovery codes were generated. Previous codes are no longer valid.',

                default => session('status'),
            };
        @endphp

        @if ($statusMessage)
            <div
                class="alert alert-success"
                role="status"
            >
                {{ $statusMessage }}
            </div>
        @endif

        @yield('content')
    </div>
</main>
</body>
</html>