<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\Operator;

beforeEach(function () {
    FailingJob::dispatch();
    $this->work();
    $this->uuid = MonitoredJob::value('uuid');
});

it('lets everybody who can view Meridian act by default', function () {
    $this->get('/meridian/jobs/failed')->assertInertia(fn (Assert $page) => $page
        ->where('meridian.can_manage', true)
        ->where('retry_all_url', route('meridian.jobs.retry-all'))
        ->where('jobs.data.0.retry_url', route('meridian.jobs.retry', $this->uuid))
    );
});

it('gives a read-only access with Meridian::manage()', function () {
    Meridian::manage(fn () => false);

    $this->get('/meridian/jobs/failed')->assertInertia(fn (Assert $page) => $page
        ->where('meridian.can_manage', false)
        ->where('retry_all_url', null)
        ->where('jobs.data.0.retry_url', null)
    );

    $this->get('/meridian/jobs/'.$this->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.retry_url', null)
        ->where('job.delete_url', null)
    );

    $this->get('/meridian/monitoring')->assertInertia(fn (Assert $page) => $page->where('store_url', null)->where('destroy_url', null));
    $this->get('/meridian/errors')->assertInertia(fn (Assert $page) => $page->where('groups.0.retry_url', null));

    $this->post("/meridian/jobs/{$this->uuid}/retry")->assertForbidden();
    $this->post('/meridian/jobs/retry')->assertForbidden();
    $this->delete("/meridian/jobs/{$this->uuid}")->assertForbidden();
    $this->post('/meridian/monitoring', ['tag' => 'customer:42'])->assertForbidden();
    $this->post('/meridian/batches/abc/retry')->assertForbidden();

    // Choosing a language is not an action on the jobs.
    $this->post('/meridian/locale', ['locale' => 'fr'])->assertRedirect();

    expect(MonitoredJob::value('status'))->toBe('failed');
});

it('uses the manageMeridian gate when defined', function () {
    Gate::define('manageMeridian', fn ($user = null) => $user?->email === 'ops@example.com');

    $this->post("/meridian/jobs/{$this->uuid}/retry")->assertForbidden();

    $this->actingAs(Operator::create(['email' => 'viewer@example.com']));
    $this->post("/meridian/jobs/{$this->uuid}/retry")->assertForbidden();

    $this->actingAs(Operator::create(['email' => 'ops@example.com']));
    $this->post("/meridian/jobs/{$this->uuid}/retry")->assertRedirect();

    expect(MonitoredJob::value('status'))->toBe('pending');
});

it('hides the actions of the Blade interface', function () {
    config()->set('meridian.ui', 'blade');
    Meridian::manage(fn () => false);

    $this->get('/meridian/jobs/failed')
        ->assertSee('Read only')
        ->assertDontSee(route('meridian.jobs.retry-all'))
        ->assertDontSee(route('meridian.jobs.retry', $this->uuid));

    $this->get('/meridian/jobs/'.$this->uuid)
        ->assertDontSee(route('meridian.jobs.retry', $this->uuid))
        ->assertDontSee(route('meridian.jobs.destroy', $this->uuid));

    $this->get('/meridian/monitoring')->assertDontSee('name="tag"', false);
    $this->get('/meridian/errors')->assertDontSee(route('meridian.jobs.retry-all'));
});
