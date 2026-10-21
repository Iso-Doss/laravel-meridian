# Metrics

The dashboard shows the slowest queue (highest average runtime) and the busiest one (most jobs processed) of the last hour. The metrics page shows, per job class and per queue, over the last hour to the last seven days:

- the jobs processed and failed, the failure rate, the average wait;
- the average, **p95**, **p99** and maximum runtime: p95 is the time under which 95 % of the jobs ran, a better view of what your users wait for than the average;
- the **trend** of the throughput, the failure rate and the runtime compared with the previous period of the same length (a failure rate or a runtime going up is shown in red);
- a **heatmap of the jobs per hour** over the last seven days, in your timezone, the hours where 10 % or more of the jobs failed outlined: spot the peaks and the nightly batches at a glance.

Each job and queue also has its history of snapshots, stored every five minutes by `meridian:snapshot`.

## Prometheus and OpenTelemetry

Meridian exposes its metrics in the Prometheus text format at `/meridian/prometheus`, for Prometheus, Grafana Alloy, the OpenTelemetry Collector (`prometheus` receiver), Datadog (OpenMetrics check) or Victoria Metrics. Enable it and give your scraper a token:

```dotenv
MERIDIAN_PROMETHEUS=true
MERIDIAN_PROMETHEUS_TOKEN=a-long-random-token
```

```yaml
scrape_configs:
  - job_name: meridian
    metrics_path: /meridian/prometheus
    authorization:
      credentials: a-long-random-token
    static_configs:
      - targets: ['app.example.com']
```

Without a token, the dashboard authorization applies (enough in local). The metrics are gauges, labelled by `connection` and `queue` or by `task`:

| Metric | Description |
| --- | --- |
| `meridian_up` | 1 running, 0.5 paused, 0 inactive |
| `meridian_queue_pending_jobs`, `_delayed_jobs`, `_reserved_jobs` | Jobs of each queue |
| `meridian_queue_wait_seconds`, `meridian_queue_processes` | Wait of the oldest job, worker processes |
| `meridian_queue_jobs_processed_last_hour`, `_failed_last_hour` | Throughput and failures of the last hour |
| `meridian_queue_runtime_avg_seconds`, `_max_seconds` | Runtime of the jobs of the last hour |
| `meridian_jobs_per_minute`, `meridian_jobs_processing` | Overall throughput, running jobs |
| `meridian_failed_jobs`, `meridian_error_groups` | Failed jobs (7 days) and distinct errors |
| `meridian_workers`, `meridian_supervisors` | Live processes by `status` |
| `meridian_schedule_task_healthy`, `_last_run_timestamp_seconds`, `_last_runtime_seconds` | Health and last run of each scheduled task |

`meridian_schedule_task_last_run_timestamp_seconds` lets your monitoring warn you when the scheduler itself stops, which Meridian cannot do from within it.
