<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LogScope — Sign in</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('logscope.theme');
                var dark = t ? t === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) document.documentElement.style.colorScheme = 'dark';
            } catch (e) {}
        })();
    </script>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center;
            justify-content: center; font-family: system-ui, -apple-system, sans-serif;
            background: #f9fafb; color: #111827;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0b0f17; color: #e5e7eb; }
            .card { background: #111827 !important; border-color: #1f2937 !important; }
            input { background: #0b0f17 !important; border-color: #374151 !important; color: #e5e7eb !important; }
        }
        .card {
            width: 100%; max-width: 22rem; padding: 2rem; border-radius: .75rem;
            background: #fff; border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(0,0,0,.06);
        }
        h1 { font-size: 1.25rem; margin: 0 0 .25rem; }
        p.sub { margin: 0 0 1.5rem; font-size: .875rem; color: #6b7280; }
        label { display: block; font-size: .8125rem; font-weight: 600; margin: 0 0 .35rem; }
        input {
            width: 100%; padding: .6rem .75rem; border: 1px solid #d1d5db;
            border-radius: .5rem; font-size: .9375rem; margin-bottom: 1rem;
        }
        input:focus { outline: 2px solid #3b82f6; outline-offset: 1px; }
        button {
            width: 100%; padding: .65rem; border: 0; border-radius: .5rem;
            background: #2563eb; color: #fff; font-weight: 600; font-size: .9375rem;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        .error { color: #dc2626; font-size: .8125rem; margin: -0.5rem 0 1rem; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ $action }}">
        @csrf
        <h1>LogScope</h1>
        <p class="sub">Sign in to view application logs.</p>

        @if ($errors->any())
            <div class="error" role="alert">{{ $errors->first() }}</div>
        @endif

        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username"
               value="{{ old('username') }}" autofocus required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password"
               autocomplete="current-password" required>

        <button type="submit">Sign in</button>
    </form>
</body>
</html>
