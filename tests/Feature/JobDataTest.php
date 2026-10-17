<?php

use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\JobData;
use IsoDoss\Meridian\Tests\Fixtures\DataJob;
use IsoDoss\Meridian\Tests\Fixtures\EncryptedJob;
use IsoDoss\Meridian\Tests\Fixtures\User;

it('shows the data of a job', function () {
    $user = User::create(['email' => 'jane@example.com']);
    DataJob::dispatch($user, 'Trial ends', ['seats' => 3, 'annual' => true]);

    $data = JobData::describe(MonitoredJob::sole()->payload);

    expect($data['error'])->toBeNull()
        ->and(collect($data['properties'])->pluck('value', 'name')->all())->toBe([
            'user' => User::class.':'.$user->id,
            'reason' => '"Trial ends"',
            'options' => '{"seats": 3, "annual": true}',
            'plan' => 'IsoDoss\Meridian\Tests\Fixtures\Plan::Pro ("pro")',
            'attemptsLeft' => '3',
        ]);
});

it('decrypts the data of encrypted jobs', function () {
    EncryptedJob::dispatch('s3cr3t');

    $payload = json_decode(MonitoredJob::sole()->payload, true);
    expect(str_starts_with($payload['data']['command'], 'O:'))->toBeFalse();

    expect(JobData::describe(MonitoredJob::sole()->payload)['properties'])->toBe([['name' => 'secret', 'value' => '"s3cr3t"']]);
});

it('explains when the data cannot be read', function () {
    $payload = json_encode(['data' => ['command' => Crypt::encrypt('O:11:"App\\Missing":0:{}')]]);

    expect(JobData::describe($payload)['error'])->toBe('The job class cannot be loaded.')
        ->and(JobData::describe(json_encode(['data' => ['command' => 'not encrypted']]))['error'])->not->toBeNull()
        ->and(JobData::describe(null))->toBeNull();
});

it('gives the data to the job page', function () {
    DataJob::dispatch(User::create(['email' => 'jane@example.com']), 'Trial ends');

    $this->get('/meridian/jobs/'.MonitoredJob::sole()->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.data.error', null)
        ->where('job.data.properties.1', ['name' => 'reason', 'value' => '"Trial ends"'])
    );

    config()->set('meridian.ui', 'blade');
    $this->get('/meridian/jobs/'.MonitoredJob::sole()->uuid)->assertSee('&quot;Trial ends&quot;', false);
});
