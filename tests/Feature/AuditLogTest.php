<?php

use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\Operator;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

function auditEntries(): array
{
    return Meridian::table('audit_logs')->orderBy('id')->get()
        ->map(fn ($row) => [$row->action, $row->subject, json_decode((string) $row->details, true), $row->user])
        ->all();
}

it('records the actions taken from the dashboard with their author', function () {
    $operator = Operator::create(['email' => 'ops@example.com']);
    FailingJob::dispatch();
    FailingJob::dispatch()->onQueue('emails');
    $this->work();
    $this->work(1, ['--queue' => 'emails']);
    SuccessfulJob::dispatch();
    [$first, $second] = MonitoredJob::where('status', 'failed')->orderBy('id')->pluck('uuid')->all();
    $pending = MonitoredJob::where('status', 'pending')->value('uuid');

    $this->actingAs($operator);
    $this->post("/meridian/jobs/{$first}/retry");
    $this->post('/meridian/jobs/retry', ['queue' => 'emails']);
    $this->delete("/meridian/jobs/{$pending}");
    $this->post('/meridian/monitoring', ['tag' => 'customer:42']);
    $this->delete('/meridian/monitoring', ['tag' => 'customer:42']);

    expect(auditEntries())->toBe([
        ['job.retried', $first, ['job' => FailingJob::class, 'queue' => 'database:default'], 'ops@example.com'],
        ['jobs.retried', null, ['count' => 1, 'queue' => 'emails'], 'ops@example.com'],
        ['job.cancelled', $pending, ['job' => SuccessfulJob::class, 'queue' => 'database:default', 'status' => 'pending'], 'ops@example.com'],
        ['tag.monitored', 'customer:42', null, 'ops@example.com'],
        ['tag.unmonitored', 'customer:42', null, 'ops@example.com'],
    ])
        ->and(Meridian::table('audit_logs')->value('ip'))->toBe('127.0.0.1')
        ->and($second)->not->toBeNull();
});

it('records the Artisan commands', function () {
    Supervisor::create([
        'name' => 'host-abcd:supervisor-1', 'master' => 'host-abcd', 'hostname' => Meridian::hostname(),
        'pid' => 1, 'status' => 'running', 'processes' => ['default' => 1], 'options' => ['connection' => 'database'],
        'started_at' => now(), 'last_seen_at' => now(),
    ]);

    $this->artisan('meridian:pause')->assertSuccessful();
    $this->artisan('meridian:continue-supervisor', ['name' => 'supervisor-1'])->assertSuccessful();
    $this->artisan('meridian:clear', ['--queue' => 'emails'])->assertSuccessful();
    $this->artisan('meridian:clear-metrics')->assertSuccessful();
    $this->artisan('meridian:forget', ['--all' => true])->assertSuccessful();

    expect(auditEntries())->toBe([
        ['meridian.paused', null, ['masters' => 1, 'hosts' => Meridian::hostname()], 'console'],
        ['supervisor.continued', 'supervisor-1', ['masters' => 1, 'hosts' => Meridian::hostname()], 'console'],
        ['queue.cleared', 'database:emails', ['count' => 0], 'console'],
        ['metrics.cleared', null, ['count' => 0], 'console'],
        ['jobs.forgotten', null, ['count' => 0], 'console'],
    ]);
});

it('does not record a command that reached no supervisor', function () {
    $this->artisan('meridian:terminate')->assertSuccessful();

    expect(auditEntries())->toBe([]);
});

it('shows the audit log', function () {
    $audit = app(AuditLogRepository::class);
    $audit->record('metrics.cleared', null, ['count' => 3]);
    $audit->record('tag.monitored', 'customer:42');

    $this->get('/meridian/audit')->assertInertia(fn (Assert $page) => $page
        ->component('Audit/Index')
        ->where('ready', true)
        ->has('entries.data', 2)
        ->where('entries.data.0.action', 'tag.monitored')
        ->where('entries.data.0.label', 'Tag monitored')
        ->where('entries.data.1.details', ['count' => 3])
        ->where('actions.0', ['value' => 'job.retried', 'label' => 'Job retried'])
    );

    $this->get('/meridian/audit?action=metrics.cleared')->assertInertia(fn (Assert $page) => $page
        ->where('filters.action', 'metrics.cleared')
        ->has('entries.data', 1)
    );

    $this->getJson('/meridian/api/audit')->assertOk()->assertJsonPath('data.0.subject', 'customer:42');

    config()->set('meridian.ui', 'blade');

    $this->withCookie('meridian_locale', 'fr')->get('/meridian/audit')
        ->assertSee('Journal d&#039;audit', false)
        ->assertSee('Tag surveillé')
        ->assertSee('count: 3');
});

it('prunes the old audit log entries', function () {
    app(AuditLogRepository::class)->record('metrics.cleared');
    Meridian::table('audit_logs')->update(['created_at' => now()->subDays(31)->getTimestamp()]);
    app(AuditLogRepository::class)->record('tag.monitored', 'recent');

    $this->artisan('meridian:prune')->expectsOutputToContain('1 audit log entries')->assertSuccessful();

    expect(auditEntries())->toHaveCount(1);
});

it('keeps working until the migration runs', function () {
    Schema::drop('meridian_audit_logs');
    Migrations::flush();

    FailingJob::dispatch();
    $this->work();
    $uuid = MonitoredJob::value('uuid');

    $this->post("/meridian/jobs/{$uuid}/retry")->assertSessionHas('meridian.success');
    $this->get('/meridian/audit')->assertInertia(fn (Assert $page) => $page->where('ready', false)->where('entries', null));
    $this->artisan('meridian:prune')->assertSuccessful();
});

it('names the error of the group retried', function () {
    FailingJob::dispatch();
    $this->work();

    $this->post('/meridian/jobs/retry', ['error' => MonitoredJob::value('fingerprint')]);

    expect(auditEntries()[0][2])->toBe(['count' => 1, 'error' => 'RuntimeException: Boom from FailingJob']);
});
