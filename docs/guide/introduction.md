# Introduction

Meridian gives the Laravel **database** queue driver what [Laravel Horizon](https://laravel.com/docs/horizon) gives Redis: a beautiful dashboard and code-driven configuration. Monitor the throughput, runtime, wait time and failures of your jobs, search them by tag, retry them in one click, and follow your batches and workers live.

All of your worker configuration lives in a single, simple configuration file. `php artisan meridian` starts and supervises your `queue:work` processes and balances them between your queues according to their workload, exactly like Horizon, so your whole team can scale the workers from source control.

**What sets Meridian apart**

- **Nothing to change in your jobs.** Every job is recorded from Laravel's own queue events: no trait, no interface, no base class. Batches, `Queue::bulk()` and `pushRaw()` jobs included.
- **A real supervisor, not only a dashboard.** `php artisan meridian` starts, restarts and auto-scales your `queue:work` processes (`auto`, `simple` or `log` strategies), with per-supervisor pause and resume, memory limit and graceful termination.
- **Horizon's features on the database driver**, without Redis and without depending on Horizon's internals: monitored tags, retry history, silenced jobs, metrics, long-wait notifications by e-mail, Slack or SMS.
- **Beyond Horizon**: failed jobs grouped by cause and retried with corrected data, scheduled tasks monitoring, alerts on failure rates, missing workers and long jobs, job progress, read-only access and an audit log.
- **Fits your tools**: Prometheus / OpenTelemetry metrics for Grafana or Datadog, a Laravel Pulse card, a Filament plugin, and `meridian:top` in the terminal.
- **Two interfaces**: a modern React / Inertia / shadcn/ui one, or a lightweight Blade one, in light, dark or system theme, in **English, French, Spanish, Portuguese, German and Arabic** (right to left).
- **Nothing to build.** The compiled assets ship with the package: your application needs neither Node nor an Inertia setup, and never conflicts with its own.
- **Production-ready**: works under a strict, nonce-based **Content-Security-Policy**, on MySQL, MariaDB, PostgreSQL and SQLite, with Laravel 12 and 13.

<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="../../art/dashboard-dark.png">
        <img src="../../art/dashboard-light.png" alt="The Meridian dashboard: jobs per minute, failures, pending jobs, throughput chart, workload per queue, supervisors and workers">
    </picture>
</p>

## Why Meridian?

Horizon only works with Redis. If your application runs its queues on the `database` driver, you usually have no visibility on what runs in the background: the `jobs` table only holds pending jobs, and a job leaves no trace once it is processed. Meridian records the whole life of every job and brings the Horizon experience to the database driver:

- **Dashboard**: jobs per minute, jobs of the past hour, failures, pending and delayed jobs, max wait time, slowest and busiest queues, throughput chart, workload per queue, supervisors and workers.
- **Job history**: pending, processing, completed and failed jobs, with their tags, readable data (the properties of the job), payload, exception, attempts, wait time and runtime.
- **Errors**: the failed jobs grouped by cause (same exception thrown at the same line), with their number, first and last occurrence; retry a whole group in one click.
- **Monitored tags**: watch a customer, an order or any tag; its jobs are listed together and kept longer.
- **Job progress**: a job reports `$this->progress(42)` and the dashboard shows a progress bar and the estimated remaining time.
- **Scheduled tasks**: every run of `schedule:run` with its status, duration and exception, and the tasks that failed or did not run when due.
- **Search** by job class, tag (`App\Models\User:42`, or anything returned by a `tags()` method) or UUID.
- **Actions**: retry one failed job, or all the failed jobs matching the filters of the list, with the history of each retry; cancel a pending or a running job; retry the failed jobs of a batch.
- **Batches**: progress of your `Bus::batch()` batches and of their jobs.
- **Metrics**: per job and per queue (processed, failure rate, average, p95, p99 and max runtime, average wait, trend against the previous period), a heatmap of the jobs per hour, and a history of snapshots.
- **Export** of the jobs and errors as CSV or JSON.
- **Workers**: every `queue:work` process reports a heartbeat (status, memory, jobs processed), even without Meridian's supervisor.
- **Supervisors**: `php artisan meridian` balances processes between queues (`auto`, `simple` or no balancing), with `meridian:pause`, `meridian:continue` and `meridian:terminate`, for all supervisors or one at a time.
- **Alerts** by e-mail, Slack, SMS or webhooks (Discord, Teams, Google Chat, any URL) when a queue waits too long, fails too often, has no worker, or when a job runs for too long.
- **Read-only access and audit log**: choose who can retry, delete or cancel jobs, and see who did what, and when.
- **A modern interface**: React, Inertia and shadcn/ui, light, dark and system themes, six languages, live refresh. Its compiled assets ship with the package: your application needs neither Node, React nor an Inertia setup of its own.

## Meridian and the other packages

Other packages cover part of this ground. Meridian is built to cover all of it in one place:

| | Meridian | Job monitors | Horizon on a database | Admin panel plugins |
| --- | --- | --- | --- | --- |
| Records every job without changing it | ✅ | Often needs a trait on each job | ✅ | Only pending and failed jobs |
| Supervises and auto-scales the workers | ✅ | ❌ | ✅ | ❌ |
| Monitored tags, retry history, metrics | ✅ | Partly | ✅ | ❌ |
| Errors grouped by cause, failure alerts, read-only access, audit log | ✅ | Partly | ❌ | ❌ |
| Scheduled tasks monitoring, retry with corrected data | ✅ | ❌ | ❌ | ❌ |
| Prometheus metrics, Pulse card, Filament plugin | ✅ | ❌ | ❌ | Filament only |
| Independent from Horizon's internals | ✅ | ✅ | ❌ (rebinds Horizon's services) | ✅ |
| Standalone dashboard, no admin panel required | ✅ | ✅ | ✅ | ❌ (Filament, Nova…) |
| Strict CSP, six languages, Blade or React | ✅ | ❌ | ❌ | Depends on the panel |

Choose Meridian if you want the full Horizon experience on the `database` driver, from the dashboard to the supervision of your workers, without Redis and without adding anything to your jobs.

## Screenshots

<table>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/jobs-dark.png">
                <img src="../../art/jobs-light.png" alt="All jobs, with the progress and remaining time of the running ones, their tags, queue and wait time">
            </picture>
            <p align="center"><b>Jobs</b>: by status, with the progress of the running ones, filtered by queue or tag</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/job-dark.png">
                <img src="../../art/job-light.png" alt="A failed job with its exception, the link to the jobs failing with the same error, its retry history and data">
            </picture>
            <p align="center"><b>Job details</b>: exception, same errors, retry history, readable data</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/errors-dark.png">
                <img src="../../art/errors-light.png" alt="Failed jobs grouped by error, with their occurrences, first and last occurrence and a retry button">
            </picture>
            <p align="center"><b>Errors</b>: failed jobs grouped by cause, retried in one click</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/schedule-dark.png">
                <img src="../../art/schedule-light.png" alt="The scheduled tasks with their status, last run, duration, recent runs and next run">
            </picture>
            <p align="center"><b>Schedule</b>: every run of your scheduled tasks, failed and missed ones flagged</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/monitoring-dark.png">
                <img src="../../art/monitoring-light.png" alt="Monitored tags with their number of jobs, pending and failed jobs">
            </picture>
            <p align="center"><b>Monitoring</b>: watch tags, keep their jobs longer</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/batches-dark.png">
                <img src="../../art/batches-light.png" alt="Job batches with their progress">
            </picture>
            <p align="center"><b>Batches</b>: progress, pending and failed jobs</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/metrics-dark.png">
                <img src="../../art/metrics-light.png" alt="Metrics per job: processed, failure rate, average and max runtime, average wait">
            </picture>
            <p align="center"><b>Metrics</b>: per job or per queue, over 1 hour to 7 days</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/audit-dark.png">
                <img src="../../art/audit-light.png" alt="The audit log: who retried, cancelled or cleared what, and when">
            </picture>
            <p align="center"><b>Audit log</b>: who did what, and when</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="../../art/blade-dark.png">
                <img src="../../art/blade-light.png" alt="The Blade interface of the dashboard, with its theme and language switchers">
            </picture>
            <p align="center"><b>Blade interface</b>: the same pages, server-rendered (<code>MERIDIAN_UI=blade</code>)</p>
        </td>
        <td width="50%">
            <img src="../../art/top.png" alt="php artisan meridian:top: queues, running jobs with their progress, supervisors, errors and scheduled tasks in the terminal">
            <p align="center"><b>Terminal</b>: <code>php artisan meridian:top</code>, live</p>
        </td>
    </tr>
</table>

The screenshots follow the theme of your GitHub account: switch it to see the other one.
