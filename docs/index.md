---
layout: home

hero:
    name: Meridian
    text: Horizon for Laravel database queues
    tagline: Dashboard, metrics and auto-scaling supervisors for the database queue driver. No Redis, nothing to change in your jobs.
    image:
        src: /icon.svg
        alt: Meridian
    actions:
        - theme: brand
          text: Get started
          link: /guide/introduction
        - theme: alt
          text: Installation
          link: /guide/installation
        - theme: alt
          text: GitHub
          link: https://github.com/Iso-Doss/laravel-meridian

features:
    - title: Every job, recorded
      details: Pending, processing, completed and failed jobs with their tags, data, exception, wait and runtime, from Laravel's own queue events. Batches and bulk jobs included.
    - title: A real supervisor
      details: php artisan meridian starts, restarts and auto-scales your queue:work processes per queue, with pause, continue, fast termination and memory limits, like Horizon.
    - title: Where every job comes from
      details: The HTTP request and user, the artisan command, the scheduled task or the parent job that dispatched each job, and the jobs it dispatched in turn.
    - title: Errors grouped by cause
      details: Failed jobs grouped by exception and line, retried in one click, or retried after correcting their data.
    - title: Scheduled tasks
      details: Every run of schedule:run with its status and duration, and the tasks that failed or did not run when they were due.
    - title: Alerts everywhere
      details: Long waits, failure rates, missing workers, long jobs and scheduled tasks, by e-mail, Slack, SMS, Discord, Teams, Google Chat or any webhook.
    - title: Fits your tools
      details: Prometheus / OpenTelemetry metrics, a Laravel Pulse card, a Filament plugin, CSV and JSON exports, and meridian:top in the terminal.
    - title: Two interfaces, six languages
      details: React / Inertia / shadcn or Blade, light and dark themes, in English, French, Spanish, Portuguese, German and Arabic.
    - title: Production-ready
      details: Secrets redacted from the stored payloads, read-only access, audit log, strict Content-Security-Policy, meridian:doctor, tested on MySQL, PostgreSQL and SQLite, Laravel 12 and 13.
---
