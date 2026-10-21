# Running Meridian

You have two options:

1. **Keep your workers.** Run `php artisan queue:work database ...` with Supervisor or systemd as usual: jobs, metrics and workers show up in the dashboard.
2. **Let Meridian supervise them**, like Horizon: run one long-lived process and Meridian starts, restarts and balances the `queue:work` processes of the current environment.

```bash
php artisan meridian
```

| Command | Description |
| --- | --- |
| `meridian` | Start the master supervisor |
| `meridian:status` | Show the status, the supervisors and the workload |
| `meridian:pause` / `meridian:continue` | Pause or resume the workers (`--all` for every host) |
| `meridian:pause-supervisor {name}` / `meridian:continue-supervisor {name}` | Pause or resume the workers of one supervisor (`--all` for every host) |
| `meridian:supervisors` | List the running supervisors |
| `meridian:supervisor-status {name}` | Show the status of a supervisor |
| `meridian:terminate` | Finish the running jobs, then stop the master supervisor (`--all` for every host) |
| `meridian:list` | List the machines running Meridian (the master supervisors) |
| `meridian:purge` | Terminate the workers left by a master that was killed, and forget the dead processes of this machine (`--signal=SIGTERM`) |
| `meridian:snapshot` | Store a metrics snapshot, import the jobs pushed without event, check the wait times |
| `meridian:prune` | Delete old completed and failed jobs, metrics and stale heartbeats |
| `meridian:forget {uuid}` / `meridian:forget --all` | Delete a failed job, or all of them, from Meridian and from the failed jobs table |
| `meridian:clear {connection?} --queue=` | Delete the jobs waiting on a queue (asks for confirmation in production, `--force` to skip it) |
| `meridian:clear-metrics` | Delete every metrics snapshot |
| `meridian:listen` | Local development: run Meridian and restart it whenever a PHP file of the application changes |
| `meridian:top` | Live view of the queues, running jobs, supervisors, errors and failing scheduled tasks in the terminal (`--interval=2`, `--once`) |

`php artisan meridian` requires the `pcntl` and `posix` extensions. When its memory exceeds `memory_limit` (64 MB by default, `0` to disable), it finishes the running jobs and exits with the code 12, so that your process monitor starts it again with fresh memory.

In local development, `php artisan meridian:listen` runs Meridian and restarts it whenever a PHP file of the directories listed in the `watch` option changes (`app`, `config`, `routes`…). Unlike `horizon:listen`, it needs neither Node nor any npm package: it polls the files every second (`--poll=` to change it).

## Deploying Meridian

Keep `php artisan meridian` running with a process monitor such as Supervisor, and run `php artisan meridian:terminate` during each deployment: the running jobs finish, Meridian exits, and the process monitor restarts it with your new code.

```ini
[program:meridian]
process_name=%(program_name)s
command=php /var/www/app/artisan meridian
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/meridian.log
stopwaitsecs=3600
```

`stopwaitsecs` must be longer than your longest job.

**Fast termination**: by default, `meridian:terminate` waits for the running jobs to finish before the master exits, which may delay your deployment. Like Horizon, set `'fast_termination' => true` to make the master exit right away: its workers finish their current job on their own, while your process monitor already starts the new master with your new code.

If a master was killed without terminating its workers (`kill -9`, out of memory...), `php artisan meridian:purge` terminates the workers it left behind.
