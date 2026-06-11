<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LogScope — Sign in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            justify-content: center; padding: 1.5rem;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            background:
                radial-gradient(1200px 600px at 50% -10%, rgba(99,102,241,.12), transparent 60%),
                #f8fafc;
            color: #1e293b;
        }
        @media (prefers-color-scheme: dark) {
            body {
                background:
                    radial-gradient(1200px 600px at 50% -10%, rgba(99,102,241,.18), transparent 60%),
                    #0a0e16;
                color: #e2e8f0;
            }
            .card { background: rgba(15,23,42,.7) !important; border-color: rgba(51,65,85,.7) !important; }
            input { background: rgba(2,6,23,.6) !important; border-color: #334155 !important; color: #e2e8f0 !important; }
            p.sub { color: #94a3b8 !important; }
        }
        .card {
            width: 100%; max-width: 23rem; padding: 2rem; border-radius: 1rem;
            background: rgba(255,255,255,.85);
            border: 1px solid #e2e8f0;
            backdrop-filter: blur(12px);
            box-shadow: 0 20px 50px -12px rgba(15,23,42,.18);
        }
        .logo {
            display: inline-flex; align-items: center; justify-content: center;
            width: 2.75rem; height: 2.75rem; border-radius: .85rem;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: #fff; margin-bottom: 1.25rem;
            box-shadow: 0 8px 20px -6px rgba(99,102,241,.6);
        }
        h1 { font-size: 1.35rem; font-weight: 700; letter-spacing: -.01em; margin: 0 0 .25rem; }
        p.sub { margin: 0 0 1.75rem; font-size: .875rem; color: #64748b; }
        label { display: block; font-size: .8125rem; font-weight: 600; margin: 0 0 .4rem; }
        input {
            width: 100%; padding: .65rem .8rem; border: 1px solid #cbd5e1;
            border-radius: .65rem; font-size: .9375rem; margin-bottom: 1.1rem;
            transition: border-color .15s, box-shadow .15s; outline: none;
        }
        input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.18); }
        button {
            width: 100%; padding: .7rem; border: 0; border-radius: .65rem;
            background: linear-gradient(135deg, #6366f1, #7c3aed); color: #fff;
            font-weight: 600; font-size: .9375rem; cursor: pointer;
            transition: filter .15s, transform .05s;
            box-shadow: 0 8px 20px -8px rgba(99,102,241,.7);
        }
        button:hover { filter: brightness(1.06); }
        button:active { transform: translateY(1px); }
        .error {
            display: flex; align-items: center; gap: .5rem;
            color: #dc2626; background: rgba(220,38,38,.08);
            border: 1px solid rgba(220,38,38,.2); border-radius: .65rem;
            padding: .6rem .75rem; font-size: .8125rem; margin: 0 0 1.25rem;
        }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ $action }}">
        @csrf
        <span class="logo" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 12h16M4 18h7"/></svg>
        </span>
        <h1>Welcome back</h1>
        <p class="sub">Sign in to LogScope to view application logs.</p>

        @if ($errors->any())
            <div class="error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
                {{ $errors->first() }}
            </div>
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
