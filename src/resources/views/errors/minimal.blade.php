<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — EduPredict</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #E8F5E9; color: #1f2937; font-family: Inter, ui-sans-serif, system-ui, sans-serif; padding: 1.5rem; }
        main { max-width: 32rem; width: 100%; background: #fff; border: 1px solid #A5D6A7; border-radius: 1rem; padding: 2rem; }
        p.code { margin: 0; color: #1B5E20; font-weight: 600; letter-spacing: 0.04em; }
        h1 { margin: 0.5rem 0 0; font-size: 1.5rem; color: #1B5E20; }
        p.message { margin: 1rem 0 0; line-height: 1.5; }
        a { color: #1B5E20; }
    </style>
</head>
<body>
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p class="message">@yield('message')</p>
        <p class="message"><a href="{{ url('/') }}">Back to EduPredict</a></p>
    </main>
</body>
</html>
