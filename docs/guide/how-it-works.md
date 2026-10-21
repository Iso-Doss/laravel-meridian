# How it works

| Horizon (Redis) | Meridian (database) |
| --- | --- |
| Jobs stored in Redis by Horizon's own queue | Laravel's queue events (`JobQueued`, `JobProcessing`, `JobProcessed`, `JobFailed`, `JobReleasedAfterException`, `JobRetryRequested`) are recorded in a `meridian_jobs` table, keyed by the job UUID |
| Workload read from Redis lists | Workload read from the `jobs` table: pending, delayed and reserved jobs, oldest available job |
| Supervisors and workers in Redis | `meridian_supervisors` and `meridian_workers` tables (heartbeats) |
| Commands through a Redis command queue | Commands written in `meridian_supervisors`, read by the master every second |
| Metrics snapshots in Redis | `meridian:snapshot` aggregates them into `meridian_metrics` |

Jobs inserted without event (`Queue::bulk()`, used by job batches, `pushRaw()`, jobs pushed by another application or before Meridian was installed) are imported from the `jobs` table when a batch is dispatched, when the dashboard is opened and at every snapshot. Recording never breaks your application: every write is guarded and its errors are only reported.

A few notes:

- Keep Meridian's tables on a dedicated connection with `MERIDIAN_DB_CONNECTION` to move the monitoring writes away from your main database. Each job costs two or three small writes.
- With **SQLite** and several workers, Laravel's own `DatabaseQueue::pop()` may hit `database is locked` errors: enable WAL and a busy timeout (`'journal_mode' => 'wal'`, `'busy_timeout' => 5000`), and prefer MySQL or PostgreSQL in production.

## Retention

`meridian:prune` deletes the completed jobs after `trim.completed` minutes (1 day by default), the failed ones after `trim.failed` minutes (7 days), the metric snapshots after `trim.metrics` minutes (7 days) and the stale heartbeats of workers and supervisors after `trim.workers` minutes, the audit log entries after `trim.audit` minutes (30 days) and the runs of the scheduled tasks after `trim.schedule` minutes (7 days). Pending and processing jobs are never pruned. Set `store_payload` to `false` to keep the table small on very busy queues.
