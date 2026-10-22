<?php

use Illuminate\Support\Facades\DB;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Redactor;
use IsoDoss\Meridian\Tests\Fixtures\EncryptedJob;
use IsoDoss\Meridian\Tests\Fixtures\LoginJob;

beforeEach(function () {
    LoginJob::$fails = true;
    LoginJob::$handled = [];
});

function failLogin(): MonitoredJob
{
    LoginJob::dispatch('ada@example.com', 'hunter2', ['api_key' => 'k-123', 'locale' => 'fr']);
    test()->work();

    return MonitoredJob::sole();
}

it('redacts the sensitive values of the stored payloads', function () {
    $job = failLogin();

    expect($job->payload)->not->toContain('hunter2')->not->toContain('k-123')
        ->toContain('ada@example.com')->toContain(Redactor::MARKER)
        ->and(LoginJob::$handled)->toBe([['ada@example.com', 'hunter2']]);

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn ($page) => $page
        ->where('job.data.properties', fn ($properties) => collect($properties)->firstWhere('name', 'password')['value'] === json_encode(Redactor::MARKER)
            && collect($properties)->firstWhere('name', 'email')['value'] === '"ada@example.com"'));
});

it('retries with the original payload of the failed jobs table', function () {
    $job = failLogin();
    LoginJob::$fails = false;

    $this->post('/meridian/jobs/'.$job->uuid.'/retry')->assertSessionHas('meridian.success');
    $this->work();

    expect(LoginJob::$handled[1])->toBe(['ada@example.com', 'hunter2']);
});

it('does not push back a redacted payload', function () {
    $job = failLogin();
    DB::table('failed_jobs')->delete();

    $this->post('/meridian/jobs/'.$job->uuid.'/retry')
        ->assertSessionHas('meridian.error', 'This job cannot be retried: its payload was not stored or was redacted.');

    expect(DB::table('jobs')->count())->toBe(0);
});

it('never shows the secrets in the edit form, and keeps them on retry', function () {
    Meridian::editJobs(fn () => true);
    $job = failLogin();
    LoginJob::$fails = false;

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn ($page) => $page
        ->where('job.edit.fields', fn ($fields) => collect($fields)->firstWhere('name', 'password') === ['name' => 'password', 'type' => 'redacted', 'value' => Redactor::MARKER, 'editable' => false]
            && ! str_contains(json_encode($fields), 'hunter2')));

    $this->post('/meridian/jobs/'.$job->uuid.'/retry-edited', ['data' => ['email' => 'grace@example.com', 'password' => 'stolen']])
        ->assertSessionHas('meridian.success');
    $this->work();

    expect(LoginJob::$handled[1])->toBe(['grace@example.com', 'hunter2'])
        ->and($job->fresh()->payload)->not->toContain('hunter2');
});

it('keeps the encrypted jobs and the payloads without sensitive keys as they are', function () {
    EncryptedJob::dispatch('password');
    $raw = DB::table('jobs')->value('payload');

    expect(MonitoredJob::sole()->payload)->toBe($raw);
});

it('can be disabled', function () {
    config()->set('meridian.redact', []);

    expect(failLogin()->payload)->toContain('hunter2');
});

it('matches the keys whatever their case and separators, with wildcards', function () {
    $redactor = new Redactor(['api_key', 'stripe_*']);

    expect($redactor->sensitive('apiKey'))->toBeTrue()
        ->and($redactor->sensitive('API-KEY'))->toBeTrue()
        ->and($redactor->sensitive('stripeSecret'))->toBeTrue()
        ->and($redactor->sensitive('apikeys'))->toBeFalse()
        ->and($redactor->sensitive('email'))->toBeFalse();
});

it('masks the whole command when it cannot be rewritten safely', function () {
    $redactor = new Redactor(['password']);

    expect($redactor->redactSerialized('O:8:"stdClass":1:{s:8:"password";s:99:"cut'))->toBe(Redactor::MARKER)
        ->and($redactor->redactSerialized('O:8:"stdClass":1:{s:4:"name";s:3:"cut'))->toBe('O:8:"stdClass":1:{s:4:"name";s:3:"cut');
});
