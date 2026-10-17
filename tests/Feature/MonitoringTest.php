<?php

use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\MonitoredTagRepository;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;
use IsoDoss\Meridian\Tests\Fixtures\TaggedJob;
use IsoDoss\Meridian\Tests\Fixtures\User;

it('monitors a tag and lists its jobs', function () {
    TaggedJob::dispatch();
    TaggedJob::dispatch();
    $this->work();

    $this->from('/meridian/monitoring')
        ->post('/meridian/monitoring', ['tag' => ' customer:42 '])
        ->assertRedirect('/meridian/monitoring')
        ->assertSessionHas('meridian.success', 'The tag customer:42 is now monitored.');

    $this->get('/meridian/monitoring')->assertInertia(fn (Assert $page) => $page
        ->component('Monitoring/Index')
        ->where('ready', true)
        ->where('tags.0.tag', 'customer:42')
        ->where('tags.0.jobs', 2)
        ->where('tags.0.pending', 1)
        ->where('tags.0.url', route('meridian.jobs.index', ['tag' => 'customer:42']))
    );

    $this->getJson('/meridian/api/monitoring')->assertOk()->assertJsonPath('0.jobs', 2);
});

it('validates the tag to monitor', function () {
    $this->post('/meridian/monitoring', ['tag' => ''])->assertSessionHasErrors('tag');
});

it('stops monitoring a tag', function () {
    app(MonitoredTagRepository::class)->monitor('App\Models\User:1');

    $this->delete('/meridian/monitoring', ['tag' => 'App\Models\User:1'])
        ->assertSessionHas('meridian.success', 'The tag App\Models\User:1 is no longer monitored.');

    expect(app(MonitoredTagRepository::class)->all())->toBe([]);
});

it('filters the jobs by exact tag, model tags included', function () {
    $user = User::create(['email' => 'jane@example.com']);
    SuccessfulJob::dispatch($user);
    SuccessfulJob::dispatch(User::create(['email' => 'john@example.com']));
    TaggedJob::dispatch();

    $this->get('/meridian/jobs?tag='.urlencode(User::class.':'.$user->id))->assertInertia(fn (Assert $page) => $page
        ->where('filters.tag', User::class.':'.$user->id)
        ->has('jobs.data', 1)
    );

    // "customer:4" must not match "customer:42".
    $this->get('/meridian/jobs?tag=customer:4')->assertInertia(fn (Assert $page) => $page->has('jobs.data', 0));
});

it('escapes the LIKE wildcards of a tag', function () {
    TaggedJob::dispatch();

    expect(MonitoredJob::query()->withTag('custom_r:42')->count())->toBe(0)
        ->and(MonitoredJob::query()->withTag('custom%')->count())->toBe(0)
        ->and(MonitoredJob::query()->withTag('customer:42')->count())->toBe(1);
});

it('keeps the jobs of a monitored tag longer when pruning', function () {
    TaggedJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(2);
    MonitoredJob::query()->update(['completed_at' => time() - 2 * 86400]);
    app(MonitoredTagRepository::class)->monitor('invoices');

    $this->artisan('meridian:prune')->assertSuccessful();

    expect(MonitoredJob::sole()->name)->toBe(TaggedJob::class);

    MonitoredJob::query()->update(['completed_at' => time() - 8 * 86400]);
    $this->artisan('meridian:prune')->assertSuccessful();

    expect(MonitoredJob::count())->toBe(0);
});

it('retries all the failed jobs of a tag', function () {
    FailingJob::dispatch();
    $this->work();
    MonitoredJob::query()->update(['tags' => json_encode(['billing'])]);
    FailingJob::dispatch();
    $this->work();

    $this->post('/meridian/jobs/retry', ['tag' => 'billing'])
        ->assertSessionHas('meridian.success', '1 failed job(s) pushed back onto the queue.');

    expect(MonitoredJob::where('status', 'failed')->count())->toBe(1);
});

it('renders the monitoring page of the Blade interface', function () {
    config()->set('meridian.ui', 'blade');
    app(MonitoredTagRepository::class)->monitor('customer:42');

    $this->get('/meridian/monitoring')
        ->assertOk()
        ->assertSee('customer:42')
        ->assertSee('action="'.route('meridian.monitoring.store').'"', false);
});
