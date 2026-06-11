<!DOCTYPE html>
<html lang="en" class="logscope-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $config['csrfToken'] }}">
    <title>LogScope</title>
    <script>
        // Apply persisted / system theme before paint to avoid a flash.
        (function () {
            try {
                var t = localStorage.getItem('logscope.theme');
                var dark = t ? t === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
        window.__LOGSCOPE__ = @json($config);
    </script>
    @foreach ($assets['css'] as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
</head>
<body>
    <div id="logscope-app"></div>

    @if ($assetsPublished)
        @foreach ($assets['js'] as $src)
            <script type="module" src="{{ $src }}"></script>
        @endforeach
    @else
        <div style="font-family:system-ui;padding:2rem;max-width:42rem;margin:0 auto;color:#374151">
            <h1 style="font-size:1.25rem;font-weight:600">LogScope assets not published</h1>
            <p style="margin-top:.5rem">Build the SPA and publish the compiled assets:</p>
            <pre style="background:#f3f4f6;padding:1rem;border-radius:.5rem;margin-top:.75rem;overflow:auto">cd packages/logscope &amp;&amp; npm install &amp;&amp; npm run build
php artisan vendor:publish --tag=logscope-assets --force</pre>
        </div>
    @endif
</body>
</html>
