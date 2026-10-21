<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Tests\Fixtures\InvoiceJob;
use IsoDoss\Meridian\Tests\Fixtures\SecretJob;
use IsoDoss\Meridian\Tests\Fixtures\User;

beforeEach(function () {
    InvoiceJob::$sent = [];
    SecretJob::$handled = [];
});

function failInvoice(): MonitoredJob
{
    InvoiceJob::dispatch(User::create(['email' => 'jane@example.com']), 'jane.example.com');
    test()->work();

    return MonitoredJob::sole();
}

it('lists the editable data of a failed job', function () {
    Meridian::editJobs(fn () => true);
    $job = failInvoice();

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.edit.url', route('meridian.jobs.retry-edited', $job->uuid))
        ->where('job.edit.fields', [
            ['name' => 'tries', 'type' => 'setting', 'value' => '1', 'editable' => false],
            ['name' => 'user', 'type' => 'object', 'value' => User::class.':1', 'editable' => false],
            ['name' => 'email', 'type' => 'string', 'value' => 'jane.example.com', 'editable' => true],
            ['name' => 'invoice', 'type' => 'int', 'value' => '42', 'editable' => true],
            ['name' => 'options', 'type' => 'json', 'value' => "{\n    \"copy\": false\n}", 'editable' => true],
            ['name' => 'notify', 'type' => 'bool', 'value' => 'true', 'editable' => true],
            ['name' => 'amount', 'type' => 'float', 'value' => '19.99', 'editable' => true],
        ])
    );
});

it('retries a failed job with corrected data', function () {
    Meridian::editJobs(fn () => true);
    $job = failInvoice();

    $this->from('/meridian/jobs/'.$job->uuid)
        ->post('/meridian/jobs/'.$job->uuid.'/retry-edited', ['data' => [
            'email' => 'jane@example.com',
            'invoice' => '42',
            'options' => '{"copy": true}',
            'notify' => 'false',
            'amount' => '20.5',
            'user' => 'ignored: not editable',
        ]])
        ->assertRedirect('/meridian/jobs/'.$job->uuid)
        ->assertSessionHas('meridian.success');

    expect($job->fresh()->status)->toBe('pending')
        ->and($job->fresh()->retries)->toBe(1)
        ->and(app('queue.failer')->find($job->uuid))->toBeNull();

    $this->work();

    expect(InvoiceJob::$sent)->toBe(['jane@example.com'])
        ->and($job->fresh()->status)->toBe('completed')
        ->and(Meridian::table('audit_logs')->value('details'))->toBe(json_encode([
            'job' => InvoiceJob::class,
            'queue' => 'database:default',
            'changed' => 'email, options, notify, amount',
        ]));
});

it('refuses invalid values', function () {
    Meridian::editJobs(fn () => true);
    $job = failInvoice();

    $this->post('/meridian/jobs/'.$job->uuid.'/retry-edited', ['data' => [
        'invoice' => 'forty-two',
        'options' => '{not json',
        'notify' => 'maybe',
        'amount' => '',
    ]])->assertSessionHasErrors(['data.invoice', 'data.options', 'data.notify', 'data.amount']);

    expect($job->fresh()->status)->toBe('failed');
});

it('edits encrypted jobs and keeps them encrypted', function () {
    Meridian::editJobs(fn () => true);
    SecretJob::dispatch('expired');
    $this->work();
    $job = MonitoredJob::sole();

    $this->post('/meridian/jobs/'.$job->uuid.'/retry-edited', ['data' => ['token' => 'fresh']])->assertSessionHas('meridian.success');

    $command = json_decode((string) $job->fresh()->payload, true)['data']['command'];
    expect($command)->not->toStartWith('O:');

    $this->work();

    expect(SecretJob::$handled)->toBe(['fresh']);
});

it('only lets the allowed people edit a job', function () {
    $job = failInvoice();

    // Nobody outside the local environment by default.
    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page->where('job.edit', null));
    $this->post('/meridian/jobs/'.$job->uuid.'/retry-edited', ['data' => ['email' => 'a@b.c']])->assertForbidden();

    Gate::define('editMeridianJobs', fn ($user = null) => true);
    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page->has('job.edit.fields'));

    // Read-only users never can.
    Meridian::manage(fn () => false);
    $this->post('/meridian/jobs/'.$job->uuid.'/retry-edited', ['data' => ['email' => 'a@b.c']])->assertForbidden();

    expect($job->fresh()->status)->toBe('failed');
});

it('offers the form in the Blade interface', function () {
    Meridian::editJobs(fn () => true);
    config()->set('meridian.ui', 'blade');
    $job = failInvoice();

    $this->get('/meridian/jobs/'.$job->uuid)
        ->assertSee('Edit and retry')
        ->assertSee('name="data[email]"', false)
        ->assertSee('value="jane.example.com"', false)
        ->assertDontSee('name="data[user]"', false);
});
