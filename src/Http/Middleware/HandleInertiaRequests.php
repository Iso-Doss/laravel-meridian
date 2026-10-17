<?php

namespace IsoDoss\Meridian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Assets;
use IsoDoss\Meridian\Support\Translator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inertia setup of the Meridian dashboard only (root view, asset version,
 * shared props), independent from the application's own Inertia setup.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'meridian::inertia';

    public function handle(Request $request, Closure $next): Response
    {
        if (Meridian::ui() !== 'inertia') {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return Assets::version();
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = Translator::locale($request);

        return array_merge(parent::share($request), [
            'meridian' => [
                'name' => config('app.name'),
                'poll' => (int) config('meridian.poll', 5),
                'locale' => $locale,
                'locales' => Translator::languages(),
                'translations' => Translator::messages($locale),
            ],
            'nav' => [
                'dashboard' => route('meridian.dashboard'),
                'jobs' => array_merge(
                    ['all' => route('meridian.jobs.index')],
                    collect(MonitoredJob::STATUSES)->mapWithKeys(fn (string $status) => [$status => route('meridian.jobs.index', $status)])->all(),
                ),
                'batches' => route('meridian.batches.index'),
                'metrics' => route('meridian.metrics.index'),
                'locale' => route('meridian.locale'),
            ],
            'flash' => [
                'success' => $request->session()->get('meridian.success'),
                'error' => $request->session()->get('meridian.error'),
            ],
        ]);
    }
}
