<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — EduPredict</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('edupredict.theme');
                var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
    <style>
        :root { --ed-canvas:#f6f7f9; --ed-surface:#fff; --ed-ink:#111827; --ed-line:#e8eaee; --ed-accent:#4f46e5; --ed-muted:#6b7280; }
        html.dark { --ed-canvas:#0b0d12; --ed-surface:#161920; --ed-ink:#e8eaee; --ed-line:#2a2e3a; --ed-accent:#818cf8; --ed-muted:#9aa3b2; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--ed-canvas); color: var(--ed-ink); font-family: Inter, ui-sans-serif, system-ui, sans-serif; padding: 1.5rem; }
        main { max-width: 32rem; width: 100%; background: var(--ed-surface); border: 1px solid var(--ed-line); border-radius: 1rem; padding: 2rem; }
        p.code { margin: 0; color: var(--ed-accent); font-weight: 600; letter-spacing: 0.04em; }
        h1 { margin: 0.5rem 0 0; font-size: 1.5rem; color: var(--ed-ink); }
        p.message { margin: 1rem 0 0; line-height: 1.5; color: var(--ed-muted); }
        a { color: var(--ed-accent); }
        .theme { position: fixed; top: 1rem; right: 1rem; border: 1px solid var(--ed-line); background: var(--ed-surface); color: var(--ed-ink); border-radius: 11px; height: 40px; width: 40px; cursor: pointer; }
        @media print { html, body, main { background: #fff !important; color: #111827 !important; } }
    </style>
</head>
<body>
    <button type="button" class="theme" data-theme-toggle aria-label="Toggle light and dark mode" onclick="(function(){var d=document.documentElement.classList.toggle('dark');document.documentElement.style.colorScheme=d?'dark':'light';try{localStorage.setItem('edupredict.theme',d?'dark':'light')}catch(e){}})()">◐</button>
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p class="message">@yield('message')</p>
        <p class="message"><a href="{{ url('/') }}">Back to EduPredict</a></p>
    </main>
</body>
</html>
