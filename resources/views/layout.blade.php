<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · ' : '' }}Meridian · {{ config('app.name') }}</title>
    @php($nonce = \IsoDoss\Meridian\Support\Assets::nonceAttribute())
    {{-- Same theme setting as the Inertia interface (light, dark or system), applied before the first paint. --}}
    <script{!! $nonce !!}>
        (function () {
            var theme = 'system';
            try { theme = localStorage.getItem('meridian.theme') || 'system'; } catch (e) {}
            var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.add(dark ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
        })();
    </script>
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
            <a href="{{ route('meridian.dashboard') }}" @class(['active' => $route === 'meridian.dashboard'])>{{ $t('Dashboard') }}</a>
            <div class="nav-title">{{ $t('Jobs') }}</div>
            <a href="{{ route('meridian.jobs.index', 'pending') }}" @class(['active' => request()->route('status') === 'pending'])>{{ $t('Pending') }}</a>
            <a href="{{ route('meridian.jobs.index', 'processing') }}" @class(['active' => request()->route('status') === 'processing'])>{{ $t('Processing') }}</a>
            <a href="{{ route('meridian.jobs.index', 'completed') }}" @class(['active' => request()->route('status') === 'completed'])>{{ $t('Completed') }}</a>
            <a href="{{ route('meridian.jobs.index', 'failed') }}" @class(['active' => request()->route('status') === 'failed'])>{{ $t('Failed') }}</a>
            <a href="{{ route('meridian.jobs.index') }}" @class(['active' => $route === 'meridian.jobs.index' && ! request()->route('status')])>{{ $t('All jobs') }}</a>
            <div class="nav-title">{{ $t('Insights') }}</div>
            <a href="{{ route('meridian.batches.index') }}" @class(['active' => str_starts_with((string) $route, 'meridian.batches')])>{{ $t('Batches') }}</a>
            <a href="{{ route('meridian.metrics.index') }}" @class(['active' => str_starts_with((string) $route, 'meridian.metrics')])>{{ $t('Metrics') }}</a>
        </nav>
        <div class="sidebar-foot">
            <div class="setting">
                <span class="setting-label">{{ $t('Theme') }}</span>
                <div class="segmented" role="group" aria-label="{{ $t('Theme') }}">
                    @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $value => $label)
                        <button type="button" data-theme-choice="{{ $value }}" aria-pressed="false">{{ $t($label) }}</button>
                    @endforeach
                </div>
            </div>
            @if (count($languages) > 1)
                <form class="setting" method="post" action="{{ route('meridian.locale') }}">
                    @csrf
                    <label class="setting-label" for="meridian-locale">{{ $t('Language') }}</label>
                    <select id="meridian-locale" name="locale" data-autosubmit>
                        @foreach ($languages as $language)
                            <option value="{{ $language['code'] }}" @selected($language['code'] === $locale)>{{ $language['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <label class="toggle"><input type="checkbox" id="meridian-poll" checked> {{ $t('Auto-refresh') }}</label>
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

{{-- No inline event handler (refused by a strict CSP): everything is wired here. --}}
<script{!! $nonce !!}>
(function () {
    var root = document.documentElement;

    // Theme: light, dark or system, kept in the browser.
    function applyTheme(theme) {
        var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        root.classList.toggle('dark', dark);
        root.classList.toggle('light', !dark);
        root.dataset.theme = theme;
        document.querySelectorAll('[data-theme-choice]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.dataset.themeChoice === theme ? 'true' : 'false');
        });
    }

    applyTheme(root.dataset.theme || 'system');

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
        if (root.dataset.theme === 'system') applyTheme('system');
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-theme-choice]');
        if (!button) return;
        try { localStorage.setItem('meridian.theme', button.dataset.themeChoice); } catch (e) {}
        applyTheme(button.dataset.themeChoice);
    });

    // Selects that submit their form, confirmations before destructive actions.
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-autosubmit]')) event.target.form.submit();
    });

    document.addEventListener('submit', function (event) {
        var message = event.target.dataset.confirm;
        if (message && !window.confirm(message)) event.preventDefault();
    });

    // Auto-refresh of the content.
    var interval = {{ (int) config('meridian.poll', 5) }} * 1000;
    var toggle = document.getElementById('meridian-poll');
    try { toggle.checked = localStorage.getItem('meridian.poll') !== '0'; } catch (e) {}
    toggle.addEventListener('change', function () {
        try { localStorage.setItem('meridian.poll', toggle.checked ? '1' : '0'); } catch (e) {}
    });

    if (!interval) { toggle.parentElement.hidden = true; return; }

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
