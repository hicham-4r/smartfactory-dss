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
        @yield('title', 'Secure Access')
        | {{ config('app.name', 'SmartFactory DSS') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body>
<div class="container-fluid auth-page">
    <div class="row min-vh-100">
        <aside
            class="col-lg-5 auth-brand-panel
                   d-flex align-items-center p-4 p-lg-5"
        >
            <div class="auth-brand-content mx-auto">
                <div class="auth-logo mb-4">
                    SF
                </div>

                <p class="text-uppercase small fw-semibold opacity-75 mb-2">
                    Enterprise Decision Support
                </p>

                <h1 class="display-6 fw-bold mb-4">
                    SmartFactory DSS
                </h1>

                <p class="lead opacity-75 mb-4">
                    Production and maintenance monitoring with secure
                    ERP integration, analytics and AI-assisted insights.
                </p>

                <div
                    class="rounded-4 border border-light
                           border-opacity-25 bg-white bg-opacity-10 p-4"
                >
                    <div class="fw-semibold mb-2">
                        Internal prototype
                    </div>

                    <div class="small opacity-75">
                        This application uses simulated ERP information
                        and does not control industrial equipment.
                    </div>
                </div>
            </div>
        </aside>

        <main
            class="col-lg-7 d-flex align-items-center
                   justify-content-center p-3 p-md-5"
        >
            <div class="auth-card p-4 p-md-5">
                @if (session('status'))
                    <div
                        class="alert alert-success"
                        role="status"
                    >
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div
                        class="alert alert-danger"
                        role="alert"
                    >
                        <div class="fw-semibold mb-2">
                            Please review the information provided.
                        </div>

                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')

                <div
                    class="border-top mt-4 pt-4
                           small text-muted-smartfactory"
                >
                    Authorized users only. Authentication activity
                    may be recorded for security and auditing.
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>