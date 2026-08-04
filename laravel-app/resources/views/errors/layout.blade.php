<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex, nofollow, noarchive"
    >

    <title>
        @yield('code')
        | SmartFactory DSS
    </title>

    <style>
        :root {
            color-scheme: light;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            place-items: center;
            background: #f3f7f8;
            color: #17242b;
            padding: 1.5rem;
        }

        main {
            width: min(100%, 42rem);
            border: 1px solid #d8e2e6;
            border-radius: 1.25rem;
            background: #ffffff;
            box-shadow: 0 1.5rem 4rem rgba(17, 45, 55, 0.12);
            padding: clamp(1.5rem, 5vw, 3rem);
        }

        .code {
            color: #0f766e;
            font-size: clamp(2.5rem, 10vw, 5rem);
            font-weight: 800;
            line-height: 1;
        }

        h1 {
            margin: 1rem 0 0.75rem;
            font-size: clamp(1.5rem, 4vw, 2.25rem);
        }

        p {
            color: #667780;
            line-height: 1.65;
        }

        a {
            display: inline-block;
            margin-top: 1rem;
            border-radius: 0.7rem;
            background: #164e63;
            color: #ffffff;
            font-weight: 700;
            padding: 0.75rem 1rem;
            text-decoration: none;
        }

        a:focus,
        a:hover {
            background: #083344;
        }
    </style>
</head>
<body>
<main>
    <div class="code">@yield('code')</div>

    <h1>@yield('heading')</h1>

    <p>@yield('message')</p>

    <a href="{{ url('/') }}">
        Return to SmartFactory DSS
    </a>
</main>
</body>
</html>
