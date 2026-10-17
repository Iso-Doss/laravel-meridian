<?php

use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Supervisor\MasterSupervisor;
use IsoDoss\Meridian\Supervisor\SupervisorOptions;

function supervisorRow(string $master, string $name, string $status = 'running'): Supervisor
{
    return Supervisor::create([
        'name' => "{$master}:{$name}", 'master' => $master, 'hostname' => Meridian::hostname(),
        'pid' => 1, 'status' => $status, 'processes' => ['default' => 2], 'options' => ['connection' => 'database', 'balance' => 'auto'],
        'started_at' => now(), 'last_seen_at' => now(),
    ]);
}

function processCommands(MasterSupervisor $master): void
{
    (fn () => $this->processPendingCommands())->call($master);
}

it('sends a command to one supervisor only', function () {
    supervisorRow('host-abcd', 'supervisor-1');
    supervisorRow('host-abcd', 'supervisor_1');
    supervisorRow('host-abcd', 'reports');

    $this->artisan('meridian:pause-supervisor', ['name' => 'supervisor-1'])->assertSuccessful();

    expect(Supervisor::whereNotNull('command')->pluck('command', 'name')->all())->toBe(['host-abcd:supervisor-1' => 'pause']);

    $this->artisan('meridian:continue-supervisor', ['name' => 'unknown'])
        ->expectsOutputToContain('No running supervisor named [unknown] found.')
        ->assertSuccessful();
});

it('applies a supervisor command to that supervisor only', function () {
    $master = new MasterSupervisor([
        SupervisorOptions::fromArray('supervisor-1', ['queue' => ['default']]),
        SupervisorOptions::fromArray('reports', ['queue' => ['reports']]),
    ]);
    $master->persist();

    Supervisor::where('name', $master->name.':reports')->update(['command' => 'pause']);
    processCommands($master);

    expect($master->supervisors[0]->paused)->toBeFalse()
        ->and($master->supervisors[1]->paused)->toBeTrue()
        ->and(Supervisor::where('name', $master->name.':reports')->value('status'))->toBe('paused')
        ->and(Supervisor::where('name', $master->name.':supervisor-1')->value('status'))->toBe('running')
        ->and(Supervisor::whereNotNull('command')->count())->toBe(0);

    // A command on every row applies to the whole master.
    Supervisor::query()->update(['command' => 'pause']);
    processCommands($master);

    expect($master->working)->toBeFalse()
        ->and($master->supervisors[0]->paused)->toBeTrue();

    Supervisor::query()->update(['command' => 'continue']);
    processCommands($master);

    expect($master->working)->toBeTrue()
        ->and($master->supervisors[1]->paused)->toBeFalse();
});

it('lists the supervisors and shows the status of one', function () {
    supervisorRow('host-abcd', 'supervisor-1');
    supervisorRow('host-abcd', 'reports', 'paused');

    $this->artisan('meridian:supervisors')
        ->expectsOutputToContain('supervisor-1')
        ->expectsOutputToContain('reports')
        ->assertSuccessful();

    $this->artisan('meridian:supervisor-status', ['name' => 'reports'])
        ->expectsOutputToContain('reports (host-abcd) is paused.')
        ->assertSuccessful();

    $this->artisan('meridian:supervisor-status', ['name' => 'missing'])->assertFailed();
});
