<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor as SupervisorModel;
use IsoDoss\Meridian\Supervisor\MasterSupervisor;
use IsoDoss\Meridian\Supervisor\ProcessInspector;
use IsoDoss\Meridian\Supervisor\SupervisorOptions;
use IsoDoss\Meridian\Supervisor\WorkerProcess;
use IsoDoss\Meridian\Tests\Fixtures\CancellableJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

/**
 * A master supervisor whose only worker runs the given command.
 */
function masterRunning(array $command): array
{
    $master = new MasterSupervisor([SupervisorOptions::fromArray('supervisor-1', ['queue' => ['default']])]);
    $process = (new WorkerProcess($command))->start();
    $master->supervisors[0]->pools['default']->processes = [$process];

    return [$master, $process];
}

function runningJob(int $pid, int $cancelledSecondsAgo): MonitoredJob
{
    SuccessfulJob::dispatch();
    $job = MonitoredJob::query()->latest('id')->first();
    $job->update(['status' => 'processing', 'worker' => Meridian::hostname().':'.$pid, 'reserved_at' => now(), 'cancel_requested_at' => time() - $cancelledSecondsAgo]);

    return $job;
}

it('asks a running job to stop', function () {
    SuccessfulJob::dispatch();
    $job = MonitoredJob::sole();
    $job->update(['status' => 'processing', 'reserved_at' => now()]);

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.delete_url', route('meridian.jobs.destroy', $job->uuid))
        ->where('job.cancelling', false)
        ->where('meridian.cancel_timeout', 10)
    );

    $this->delete('/meridian/jobs/'.$job->uuid)
        ->assertSessionHas('meridian.success', 'Cancellation requested: the job stops at its next check, or its worker is stopped within 10 seconds when Meridian supervises it.');

    expect($job->fresh()->cancel_requested_at)->not->toBeNull()
        ->and(Meridian::table('audit_logs')->value('action'))->toBe('job.cancelled');

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.cancelling', true)
        ->where('job.delete_url', null)
    );

    config()->set('meridian.ui', 'blade');
    $this->get('/meridian/jobs/processing')->assertSee('Cancelling…');
});

it('lets a job stop by itself when it is cancelled', function () {
    CancellableJob::$stoppedAt = null;
    CancellableJob::dispatch();
    MonitoredJob::query()->update(['cancel_requested_at' => time()]);

    $this->work();

    expect(CancellableJob::$stoppedAt)->toBe(1)
        ->and(MonitoredJob::count())->toBe(0);
});

it('runs a job normally when it is not cancelled', function () {
    CancellableJob::$stoppedAt = null;
    CancellableJob::dispatch();

    $this->work();

    expect(CancellableJob::$stoppedAt)->toBeNull()
        ->and(MonitoredJob::sole()->status)->toBe('completed');
});

it('stops the worker of a cancelled job that does not stop by itself', function () {
    [$master, $process] = masterRunning(['sleep', '30']);
    $job = runningJob($process->pid(), cancelledSecondsAgo: 15);
    $jobId = DB::table('jobs')->value('id');
    $job->update(['job_id' => $jobId]);

    (fn () => $this->stopCancelledJobs())->call($master);
    usleep(200_000);

    expect($process->isRunning())->toBeFalse()
        ->and(MonitoredJob::count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('leaves the cancelled jobs it does not run, or still within their timeout', function () {
    [$master, $process] = masterRunning(['sleep', '30']);
    runningJob($process->pid(), cancelledSecondsAgo: 2);
    $other = runningJob(999999, cancelledSecondsAgo: 60);

    (fn () => $this->stopCancelledJobs())->call($master);

    expect($process->isRunning())->toBeTrue()
        ->and(MonitoredJob::count())->toBe(2);

    $process->signal(SIGKILL);
});

it('exits right away with fast termination, the workers finishing on their own', function () {
    config()->set('meridian.fast_termination', true);
    [$master, $process] = masterRunning(['sh', '-c', 'trap "" TERM; sleep 2']);
    $pid = $process->pid();
    usleep(300_000); // Let the shell install its trap: the worker ignores SIGTERM while it finishes its job.

    $started = microtime(true);
    (fn () => $this->shutdown())->call($master);
    unset($master, $process);
    gc_collect_cycles();

    expect(microtime(true) - $started)->toBeLessThan(1)
        ->and(posix_kill($pid, 0))->toBeTrue();

    posix_kill($pid, SIGKILL);
});

it('waits for the workers without fast termination', function () {
    [$master, $process] = masterRunning(['sleep', '30']);

    (fn () => $this->shutdown())->call($master);

    expect($process->isRunning())->toBeFalse();
});

it('purges the workers left by a master that is gone', function () {
    $artisan = base_path('artisan');
    $inspector = new class($artisan) extends ProcessInspector
    {
        public array $killed = [];

        public function __construct(protected string $artisan) {}

        public function processes(): array
        {
            return [
                ['pid' => 100, 'ppid' => 1, 'command' => "/usr/bin/php {$this->artisan} meridian"],
                ['pid' => 101, 'ppid' => 100, 'command' => "/usr/bin/php {$this->artisan} queue:work database --name=supervisor-1 --queue=default"],
                ['pid' => 102, 'ppid' => 1, 'command' => "/usr/bin/php {$this->artisan} queue:work database --name=supervisor-1 --queue=emails"],
                ['pid' => 103, 'ppid' => 1, 'command' => "/usr/bin/php {$this->artisan} queue:work database --name=default"],
                ['pid' => 104, 'ppid' => 1, 'command' => "/usr/bin/php {$this->artisan} meridian:top"],
            ];
        }

        public function isRunning(int $pid): bool
        {
            return in_array($pid, array_column($this->processes(), 'pid'), true);
        }

        public function kill(int $pid, int $signal): bool
        {
            $this->killed[] = [$pid, $signal];

            return true;
        }
    };
    app()->instance(ProcessInspector::class, $inspector);

    SupervisorModel::create([
        'name' => 'gone:supervisor-1', 'master' => 'gone', 'hostname' => Meridian::hostname(), 'pid' => 999,
        'status' => 'running', 'processes' => [], 'options' => [], 'started_at' => now(), 'last_seen_at' => now(),
    ]);
    Meridian::table('workers')->insert([
        ['name' => Meridian::hostname().':101', 'hostname' => Meridian::hostname(), 'pid' => 101, 'connection' => 'database', 'queue' => 'default', 'status' => 'idle', 'started_at' => time(), 'last_seen_at' => time()],
        ['name' => Meridian::hostname().':998', 'hostname' => Meridian::hostname(), 'pid' => 998, 'connection' => 'database', 'queue' => 'default', 'status' => 'idle', 'started_at' => time(), 'last_seen_at' => time()],
    ]);

    $this->artisan('meridian:purge')
        ->expectsOutputToContain('1 orphaned worker(s) terminated, 2 dead process(es) forgotten.')
        ->assertSuccessful();

    expect($inspector->killed)->toBe([[102, SIGTERM]])
        ->and(Meridian::table('workers')->pluck('pid')->all())->toBe([101])
        ->and(SupervisorModel::count())->toBe(0);

    $this->artisan('meridian:purge', ['--signal' => 'KILL'])->assertSuccessful();
    expect($inspector->killed[1])->toBe([102, SIGKILL]);

    $this->artisan('meridian:purge', ['--signal' => 'NOPE'])->assertFailed();
});

it('lists the machines running Meridian', function () {
    foreach (['supervisor-1' => 5, 'reports' => 2] as $name => $processes) {
        SupervisorModel::create([
            'name' => "web-01-ab12:{$name}", 'master' => 'web-01-ab12', 'hostname' => 'web-01', 'pid' => 4127,
            'status' => 'running', 'processes' => ['default' => $processes], 'options' => [], 'started_at' => now()->subHour(), 'last_seen_at' => now(),
        ]);
    }

    $this->artisan('meridian:list')
        ->expectsTable(['Master', 'Host', 'PID', 'Supervisors', 'Processes', 'Status', 'Started'], [
            ['web-01-ab12', 'web-01', 4127, 'reports, supervisor-1', 7, 'running', '1 hour ago'],
        ])
        ->assertSuccessful();

    SupervisorModel::query()->delete();
    $this->artisan('meridian:list')->expectsOutputToContain('No master supervisor is running.')->assertSuccessful();
});
