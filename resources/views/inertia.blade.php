@use('IsoDoss\Meridian\Support\Assets')
<!DOCTYPE html>
<html lang="{{ $page['props']['meridian']['locale'] ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Meridian · {{ config('app.name') }}</title>
    @php($nonce = Assets::nonceAttribute())
    {{-- The nonce of the application's Content-Security-Policy, if any, on every script and style. --}}
    <meta property="csp-nonce"{!! $nonce !!}>
    <script{!! $nonce !!}>
        (function () {
            var theme = 'system';
            try { theme = localStorage.getItem('meridian.theme') || 'system'; } catch (e) {}
            var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    @php($assets = Assets::entry())
    @foreach ($assets['css'] as $css)
        <link rel="stylesheet"{!! $nonce !!} href="{{ route('meridian.assets', ['path' => \Illuminate\Support\Str::after($css, 'assets/')]) }}">
    @endforeach
    @foreach ($assets['js'] as $js)
        <script type="module"{!! $nonce !!} src="{{ route('meridian.assets', ['path' => \Illuminate\Support\Str::after($js, 'assets/')]) }}"></script>
    @endforeach
</head>
<body class="min-h-screen bg-background font-sans text-foreground antialiased">
    @if ($assets['js'] === [])
        <p style="padding:2rem;font-family:sans-serif">Meridian's assets are missing (dist/). Run <code>npm run build</code> in the package, or set <code>MERIDIAN_UI=blade</code>.</p>
    @endif
    {{-- Written by hand instead of @inertia so that the application's SSR server never renders Meridian's pages. --}}
    <script data-page="meridian" type="application/json">{!! json_encode($page, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <div id="meridian"></div>
</body>
</html>
