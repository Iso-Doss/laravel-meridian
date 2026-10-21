# Job progress

Long jobs can report their progress with the `IsoDoss\Meridian\Concerns\TracksProgress` trait: the dashboard shows a progress bar, the estimated remaining time and an optional message while they run.

```php
use IsoDoss\Meridian\Concerns\TracksProgress;

class ImportCustomers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TracksProgress;

    public function handle(): void
    {
        foreach ($this->rows as $index => $row) {
            // ...

            $this->progressOf($index + 1, count($this->rows), ($index + 1).' customers imported');
        }
    }
}
```

`$this->progress(42)` takes a percentage, `$this->progressOf($done, $total)` computes it. Meridian only writes when the percentage or the message changes, so you may call them in a tight loop.

## Cancelling a running job

« Cancel job » also works on a running job. The job is asked to stop:

- a job using `TracksProgress` stops cleanly when it checks `$this->cancelled()` (one query per second at most), then Meridian forgets it:

  ```php
  foreach ($this->rows as $row) {
      if ($this->cancelled()) {
          return;
      }

      // ...
  }
  ```

- otherwise, when the job runs under `php artisan meridian`, its master stops the worker running it after `cancel_timeout` seconds (10 by default), removes the job from the queue and starts a new worker.

A job run by a worker that Meridian does not supervise, and that does not check `$this->cancelled()`, runs to completion.
