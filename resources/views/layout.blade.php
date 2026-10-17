<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Meridian{{ isset($title) ? ' · '.$title : '' }} · {{ config('app.name') }}</title>
    @php($nonce = \IsoDoss\Meridian\Support\Assets::nonceAttribute())
    <style{!! $nonce !!}>@include('meridian::partials.styles')</style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <a href="{{ route('meridian.dashboard') }}" class="brand">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M2 12h20M12 2c3 3.5 3 16.5 0 20M12 2c-3 3.5-3 16.5 0 20" stroke="currentColor" stroke-width="1.5"/></svg>
            <span>Meridian</span>
        </a>
        @php($route = request()->route()?->getName())
        <nav>
            <a href="{{ route('meridian.dashboard') }}" @class(['active' => $route === 'meridian.dashboard'])>Dashboard</a>
            <div class="nav-title">Jobs</div>
            <a href="{{ route('meridian.jobs.index', 'pending') }}" @class(['active' => request()->route('status') === 'pending'])>Pending</a>
            <a href="{{ route('meridian.jobs.index', 'processing') }}" @class(['active' => request()->route('status') === 'processing'])>Processing</a>
            <a href="{{ route('meridian.jobs.index', 'completed') }}" @class(['active' => request()->route('status') === 'completed'])>Completed</a>
            <a href="{{ route('meridian.jobs.index', 'failed') }}" @class(['active' => request()->route('status') === 'failed'])>Failed</a>
            <a href="{{ route('meridian.jobs.index') }}" @class(['active' => $route === 'meridian.jobs.index' && ! request()->route('status')])>All jobs</a>
            <div class="nav-title">Insights</div>
            <a href="{{ route('meridian.batches.index') }}" @class(['active' => str_starts_with((string) $route, 'meridian.batches')])>Batches</a>
            <a href="{{ route('meridian.metrics.index') }}" @class(['active' => str_starts_with((string) $route, 'meridian.metrics')])>Metrics</a>
        </nav>
        <div class="sidebar-foot">
            <label class="toggle"><input type="checkbox" id="meridian-poll" checked> Auto-refresh</label>
        </div>
    </aside>

    <main class="main">
        @if (session('meridian.success'))
            <div class="flash success">{{ session('meridian.success') }}</div>
        @endif
        @if (session('meridian.error'))
            <div class="flash error">{{ session('meridian.error') }}</div>
        @endif

        <div id="meridian-content" @if(! empty($poll)) data-poll @endif>
            @yield('content')
        </div>
    </main>
</div>

<script{!! $nonce !!}>
(function () {
    var interval = {{ (int) config('meridian.poll', 5) }} * 1000;
    var toggle = document.getElementById('meridian-poll');
    try { toggle.checked = localStorage.getItem('meridian.poll') !== '0'; } catch (e) {}
    toggle.addEventListener('change', function () {
        try { localStorage.setItem('meridian.poll', toggle.checked ? '1' : '0'); } catch (e) {}
    });

    if (!interval) { toggle.parentElement.style.display = 'none'; return; }

    setInterval(function () {
        var region = document.querySelector('[data-poll]');
        if (!region || !toggle.checked || document.hidden) return;

        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.text() : null; })
            .then(function (html) {
                if (!html) return;
                var next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-poll]');
                if (next) region.innerHTML = next.innerHTML;
            })
            .catch(function () {});
    }, interval);
})();
</script>
</body>
</html>
