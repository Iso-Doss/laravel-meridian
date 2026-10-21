# Configuration

After installing Meridian, its configuration lives in `config/meridian.php`. Each option is documented in the file itself.

## Database

Meridian stores its data in its own tables (`meridian_*`). By default they live on the application's default connection; set `MERIDIAN_DB_CONNECTION` to move them to another one, and keep the monitoring writes away from your main database.

```ini
MERIDIAN_DB_CONNECTION=monitoring
MERIDIAN_PRIMARY_KEY=uuid   # "uuid" (default), "ulid" or "id"
```

`MERIDIAN_PRIMARY_KEY` sets the type of the `id` column created by the migrations: UUIDv7 by default, ULID, or an auto-incrementing integer. Choose it before running the migrations; see [Primary keys](./installation#primary-keys).

## Environments and supervisors

The `environments` option lists, for each environment of your application, the supervisors to run. A supervisor manages a group of worker processes processing one or more queues. The supervisors of the current environment (`APP_ENV`) are started; a `'*'` environment is used when none matches.

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'database',
            'queue' => ['default', 'emails', 'images'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
        ],

        'reports' => [
            'connection' => 'database',
            'queue' => ['reports'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'timeout' => 300,
        ],
    ],

    'local' => [
        'supervisor-1' => [
            'maxProcesses' => 3,
        ],
    ],
],
```

The `defaults` option holds the values shared by every supervisor of the same name, merged with the values of the environment.

## Balancing strategies

| `balance` | Behaviour |
| --- | --- |
| `auto` (default) | Each queue gets its own pool of processes; Meridian moves processes towards the queues with the most work, between `minProcesses` per queue and `maxProcesses` in total. With `autoScalingStrategy: time` (default) the work of a queue is its pending jobs × their average runtime, with `size` only its number of pending jobs, and with `log` the logarithm of that number, so that one huge queue does not take every process. `balanceMaxShift` processes are added or removed every `balanceCooldown` seconds. |
| `simple` | `maxProcesses` is split evenly between the queues. |
| `false` | `maxProcesses` workers process the queues in the order they are listed, like `queue:work --queue=high,default`. |

Like with Horizon, the order of the queues does not set priorities with `auto` balancing: give a resource-heavy queue its own supervisor with a low `maxProcesses`.

## Worker options

| Option | Default | `queue:work` option |
| --- | --- | --- |
| `tries` | `1` | `--tries` |
| `timeout` | `60` | `--timeout` (keep it a few seconds below `retry_after`) |
| `backoff` | `0` | `--backoff` |
| `memory` | `128` | `--memory` |
| `sleep` | `3` | `--sleep` |
| `maxJobs` | `0` | `--max-jobs` |
| `maxTime` | `0` | `--max-time` |
| `rest` | `0` | `--rest` |
| `nice` | `0` | runs the workers with `nice -n` |
| `force` | `false` | `--force` (process jobs in maintenance mode) |

## Silenced jobs

Completed jobs listed in the `silenced` option, implementing `IsoDoss\Meridian\Contracts\Silenced`, or carrying a tag of the `silenced_tags` option leave the « Completed » list for the « Silenced » one, like with Horizon. They still count in the metrics and are pruned like the completed jobs. Their failures are always listed with the other failures.

```php
'silenced' => [
    App\Jobs\PingHealthCheck::class,
],

'silenced_tags' => [
    'notifications',
],
```
