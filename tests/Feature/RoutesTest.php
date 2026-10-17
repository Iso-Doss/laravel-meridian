<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use IsoDoss\Meridian\Http\Middleware\Authorize;
use IsoDoss\Meridian\MeridianServiceProvider;

function reloadMeridianRoutes(): void
{
    Route::setRoutes(new RouteCollection);

    $provider = app()->getProvider(MeridianServiceProvider::class);
    (fn () => $this->registerRoutes())->call($provider);

    Route::getRoutes()->refreshNameLookups();
}

it('registers the web and api routes with the default prefixes', function () {
    expect(route('meridian.dashboard', absolute: false))->toBe('/meridian')
        ->and(route('meridian.jobs.index', 'failed', false))->toBe('/meridian/jobs/failed')
        ->and(route('meridian.api.stats', absolute: false))->toBe('/meridian/api/stats');
});

it('uses the prefixes, domain and middleware from the configuration', function () {
    config()->set('meridian.route.web', ['prefix' => 'admin/queues', 'domain' => 'ops.test', 'middleware' => ['web', 'throttle:60,1']]);
    config()->set('meridian.route.api', ['prefix' => '/api/v1/queues/', 'domain' => '', 'middleware' => []]);

    reloadMeridianRoutes();

    $dashboard = Route::getRoutes()->getByName('meridian.dashboard');
    $stats = Route::getRoutes()->getByName('meridian.api.stats');

    expect($dashboard->uri())->toBe('admin/queues')
        ->and($dashboard->getDomain())->toBe('ops.test')
        ->and($dashboard->gatherMiddleware())->toContain('web', 'throttle:60,1', Authorize::class)
        ->and($stats->uri())->toBe('api/v1/queues/stats')
        ->and($stats->getDomain())->toBeNull()
        ->and($stats->gatherMiddleware())->toBe([Authorize::class]);
});
