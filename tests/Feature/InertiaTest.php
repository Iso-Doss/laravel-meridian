<?php

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Middleware as InertiaMiddleware;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Http\Controllers\MetricsController;
use IsoDoss\Meridian\MeridianServiceProvider;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Assets;
use IsoDoss\Meridian\Support\Format;
use IsoDoss\Meridian\Support\Translator;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;
use IsoDoss\Meridian\Tests\Fixtures\TaggedJob;

it('renders every page with its Inertia component', function (string $uri, string $component, array $props) {
    SuccessfulJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);
    TaggedJob::dispatch();

    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->hasAll($props)
            ->has('meridian.translations')
            ->has('nav.jobs.failed')
        );
})->with([
    'dashboard' => ['/meridian', 'Dashboard', ['stats', 'workload', 'throughput', 'supervisors', 'workers']],
    'all jobs' => ['/meridian/jobs', 'Jobs/Index', ['jobs.data', 'jobs.meta', 'counts', 'queues', 'filters']],
    'failed jobs' => ['/meridian/jobs/failed', 'Jobs/Index', ['jobs.data', 'counts']],
    'batches' => ['/meridian/batches', 'Batches/Index', ['batches']],
    'metrics' => ['/meridian/metrics', 'Metrics/Index', ['rows', 'periods', 'tabs']],
    'queue metrics' => ['/meridian/metrics/queues?period=60', 'Metrics/Index', ['rows']],
    'job metrics' => ['/meridian/metrics/jobs/'.urlencode(SuccessfulJob::class), 'Metrics/Show', ['snapshots', 'back_url']],
    'errors' => ['/meridian/errors', 'Errors/Index', ['groups', 'ready', 'queues', 'filters']],
    'audit log' => ['/meridian/audit', 'Audit/Index', ['entries', 'ready', 'filters', 'actions']],
]);

it('shows the stats and the jobs of the dashboard', function () {
    SuccessfulJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);
    SuccessfulJob::dispatch()->onQueue('emails');

    $this->get('/meridian')->assertInertia(fn (Assert $page) => $page
        ->where('stats.pendingJobs', 1)
        ->where('stats.recentJobs', 2)
        ->where('workload', fn ($workload) => collect($workload)->contains(fn ($queue) => $queue['queue'] === 'emails' && $queue['pending'] === 1))
        ->has('throughput', 60)
    );

    $this->get('/meridian/jobs/failed')->assertInertia(fn (Assert $page) => $page
        ->where('status', 'failed')
        ->where('counts.failed', 1)
        ->has('jobs.data', 1)
        ->where('jobs.data.0.short_name', 'FailingJob')
    );
});

it('filters the jobs by search and queue', function () {
    TaggedJob::dispatch();
    SuccessfulJob::dispatch()->onQueue('emails');

    $this->get('/meridian/jobs?search=customer:42')->assertInertia(fn (Assert $page) => $page
        ->where('filters.search', 'customer:42')
        ->has('jobs.data', 1)
        ->where('jobs.data.0.tags', ['invoices', 'customer:42'])
    );

    $this->get('/meridian/jobs?queue=emails')->assertInertia(fn (Assert $page) => $page
        ->has('jobs.data', 1)
        ->where('jobs.data.0.queue', 'emails')
    );
});

it('shows a failed job with its actions', function () {
    FailingJob::dispatch();
    $this->work();
    $job = MonitoredJob::sole();

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page
        ->component('Jobs/Show')
        ->where('job.uuid', $job->uuid)
        ->where('job.exception', fn ($exception) => str_contains($exception, 'Boom from FailingJob'))
        ->where('job.retry_url', route('meridian.jobs.retry', $job->uuid))
        ->where('job.delete_url', route('meridian.jobs.destroy', $job->uuid))
    );
});

it('shows a batch with its jobs', function () {
    $batch = Bus::batch([new SuccessfulJob, new SuccessfulJob])->name('Import')->dispatch();

    $this->get('/meridian/batches')->assertInertia(fn (Assert $page) => $page
        ->where('batches.0.name', 'Import')
        ->where('batches.0.status', 'running')
    );

    $this->get('/meridian/batches/'.$batch->id)->assertInertia(fn (Assert $page) => $page
        ->component('Batches/Show')
        ->where('batch.total', 2)
        ->has('jobs.data', 2)
    );
});

it('shares the flash messages in the language of the visitor', function () {
    FailingJob::dispatch();
    $this->work();
    $job = MonitoredJob::sole();

    $this->withCookie(Translator::COOKIE, 'fr')
        ->from('/meridian/jobs/'.$job->uuid)
        ->post('/meridian/jobs/'.$job->uuid.'/retry')
        ->assertRedirect('/meridian/jobs/'.$job->uuid);

    $this->withCookie(Translator::COOKIE, 'fr')
        ->get('/meridian/jobs/'.$job->uuid)
        ->assertInertia(fn (Assert $page) => $page
            ->where('meridian.locale', 'fr')
            ->where('meridian.translations.Dashboard', 'Tableau de bord')
            ->where('flash.success', 'Le job a été remis dans la file.')
        );
});

it('uses the application locale by default', function () {
    app()->setLocale('fr');

    $this->get('/meridian')->assertInertia(fn (Assert $page) => $page->where('meridian.locale', 'fr'));

    app()->setLocale('it');

    $this->get('/meridian')->assertInertia(fn (Assert $page) => $page
        ->where('meridian.locale', 'en')
        ->where('meridian.locales.0', ['code' => 'en', 'label' => 'English'])
        ->where('meridian.locales.5', ['code' => 'ar', 'label' => 'العربية'])
    );
});

it('remembers the chosen language', function () {
    $this->from('/meridian/jobs')
        ->post('/meridian/locale', ['locale' => 'fr'])
        ->assertRedirect('/meridian/jobs')
        ->assertCookie(Translator::COOKIE, 'fr');

    $this->post('/meridian/locale', ['locale' => 'it'])->assertSessionHasErrors('locale');
});

it('asks the browser to reload when the assets changed', function () {
    $this->get('/meridian', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'outdated'])
        ->assertStatus(409);
});

it('serves the compiled assets', function () {
    $entry = Assets::entry();

    expect($entry['js'])->not->toBeEmpty();

    $this->get(route('meridian.assets', ['path' => str_replace('assets/', '', $entry['js'][0])]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $this->get('/meridian/assets/missing.js')->assertNotFound();
    $this->get('/meridian/assets/../../composer.json')->assertNotFound();
    $this->get('/meridian/assets/%2E%2E/.vite/manifest.json')->assertNotFound();
});

it('loads the assets in the root view', function () {
    $this->get('/meridian')
        ->assertSee('data-page="meridian"', false)
        ->assertSee('/meridian/assets/'.str_replace('assets/', '', Assets::entry()['js'][0]), false);
});

it('puts the CSP nonce of the application on its scripts and styles', function () {
    Vite::useCspNonce('meridian-test-nonce');

    $html = $this->get('/meridian')->assertOk()->getContent();

    // The meta tag read by the client, the theme script, the stylesheet and the module script.
    expect(substr_count($html, 'nonce="meridian-test-nonce"'))->toBe(3 + count(Assets::entry()['css']))
        ->and($html)->toContain('<meta property="csp-nonce" nonce="meridian-test-nonce">')
        ->and($html)->toMatch('/<script type="module" nonce="meridian-test-nonce" src=/');
});

it('adds no nonce when the application uses none', function () {
    $this->get('/meridian')->assertOk()->assertDontSee('nonce=', false);
});

it('does not run the application Inertia middleware on its pages', function () {
    Route::pushMiddlewareToGroup('web', ApplicationInertiaMiddleware::class);
    Route::setRoutes(new RouteCollection);
    (fn () => $this->registerRoutes())->call(app()->getProvider(MeridianServiceProvider::class));
    Route::getRoutes()->refreshNameLookups();

    $this->get('/meridian')->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->missing('application')
    );
});

it('keeps the Blade interface when configured', function () {
    config()->set('meridian.ui', 'blade');

    $this->get('/meridian')->assertOk()->assertSee('Current workload')->assertDontSee('data-page="meridian"', false);
});

it('translates every text of both interfaces in every language', function (string $language) {
    $keys = collect(glob(__DIR__.'/../../resources/js/{,*/,*/*/}*.{ts,tsx}', GLOB_BRACE))
        ->flatMap(function (string $file) {
            $source = file_get_contents($file);
            preg_match_all("/\\bt\\(\\s*'((?:[^'\\\\]|\\\\.)+)'/", $source, $calls);
            preg_match_all("/(?:label|title):\\s*'([^']+)'/", $source, $labels);
            preg_match_all('/(?:LABELS|TITLES)[^=]*=\\s*\\{(.*?)\\};/s', $source, $maps);
            $mapped = collect($maps[1])->flatMap(fn ($map) => preg_match_all("/:\\s*'([^']+)'/", $map, $m) ? $m[1] : []);

            return array_merge($calls[1], $labels[1], $mapped->all());
        })
        ->merge(array_values(MetricsController::PERIODS))
        // The Blade interface: its $t('...') calls, the label maps of its views and the statuses.
        ->merge(collect(glob(__DIR__.'/../../resources/views/{,*/}*.blade.php', GLOB_BRACE))->flatMap(function (string $file) {
            $source = file_get_contents($file);
            preg_match_all("/\\\$t\\('((?:[^'\\\\]|\\\\.)+)'/", $source, $calls);
            preg_match_all("/=>\\s*'([A-Z][^']*)'/", $source, $labels);

            return array_merge($calls[1], $labels[1]);
        }))
        ->merge(array_values(Format::STATUSES))
        ->unique()
        ->values();

    $missing = $keys->diff(array_keys(Translator::messages($language)))->values()->all();

    expect($keys)->not->toBeEmpty()
        ->and($missing)->toBe([]);
})->with(['fr', 'es', 'pt', 'de', 'ar']);

it('keeps the same texts and placeholders in every language', function () {
    $reference = Translator::messages('fr');

    foreach (['es', 'pt', 'de', 'ar'] as $language) {
        $messages = Translator::messages($language);

        expect(array_keys($messages))->toEqualCanonicalizing(array_keys($reference));

        foreach ($messages as $key => $text) {
            preg_match_all('/:[a-z]+/', $key, $expected);
            preg_match_all('/:[a-z]+/', $text, $actual);

            expect($actual[0])->toEqualCanonicalizing($expected[0], "[{$language}] {$key}");
        }
    }
});

it('writes Arabic from right to left', function () {
    $this->withCookie(Translator::COOKIE, 'ar')->get('/meridian')
        ->assertSee('dir="rtl"', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('meridian.direction', 'rtl')
            ->where('meridian.translations.Dashboard', 'لوحة التحكم')
        );

    config()->set('meridian.ui', 'blade');

    $this->withCookie(Translator::COOKIE, 'de')->get('/meridian')
        ->assertSee('<html lang="de" dir="ltr">', false)
        ->assertSee('Warteschlangen');
});

class ApplicationInertiaMiddleware extends InertiaMiddleware
{
    public function share(Request $request): array
    {
        return ['application' => 'shared by the application'];
    }
}

it('shows the slowest and the busiest queues of the last hour', function () {
    SuccessfulJob::dispatch();
    SuccessfulJob::dispatch()->onQueue('emails');
    SuccessfulJob::dispatch()->onQueue('emails');
    $this->work(1);
    $this->work(2, ['--queue' => 'emails']);
    MonitoredJob::where('queue', 'default')->update(['runtime' => 5000]);
    MonitoredJob::where('queue', 'emails')->update(['runtime' => 100]);

    $this->getJson('/meridian/api/stats')
        ->assertJsonPath('maxRuntime', ['queue' => 'database:default', 'runtime' => 5000])
        ->assertJsonPath('maxThroughput', ['queue' => 'database:emails', 'count' => 2]);
});
